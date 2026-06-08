<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\PasswordReset;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Rules\ValidImageType;
use App\Models\ForgetPassword;
use App\Trait\FileHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public $fileHandler;

    public function __construct(FileHandler $fileHandler)
    {
        $this->fileHandler = $fileHandler;
    }

    public function login(Request $request)
    {
        if ($request->isMethod("post")) {

            $request->validate([
                "email"    => "required",
                "password" => "required",
            ]);

            // FIX SEC: Rate limiting на login — 5 попыток за 1 минуту
            // Риск: без ограничений — brute force на любой аккаунт
            $key = "login-attempt:" . $request->ip() . "|" . $request->email;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->with(
                    "error",
                    "Too many login attempts. Please try again in {$seconds} seconds."
                );
            }

            if (!Auth::validate($request->only("email", "password"))) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->with("error", "Incorrect email or password");
            }

            $user = User::where("email", $request->email)->first();
            if ($user->is_suspended == 1) {
                return redirect()->back()->with("error", "Your account is temporarily suspended");
            }

            RateLimiter::clear($key);

            $remember    = $request->remember_me ? true : false;
            $credentials = ["email" => $request->email, "password" => $request->password];

            if (Auth::attempt($credentials, $remember)) {
                session()->regenerate();
                return $this->redirectUser();
            } else {
                return redirect()->route("login")->with("error", "Incorrect email or password");
            }
        } else {
            if (auth()->user()) {
                return $this->redirectUser();
            } else {
                return view("frontend.authentication.login");
            }
        }
    }

    public function register(Request $request)
    {
        if ($request->isMethod("post")) {
            $request->validate([
                "name"     => "required",
                "email"    => "email|required|unique:users",
                "password" => "required|confirmed|min:8",
            ]);

            $newUser = User::create([
                "name"     => $request->name,
                "email"    => $request->email,
                "password" => bcrypt($request->password),
                "username" => uniqid(),
            ]);

            if ($newUser) {
                $cashierRole = Role::where("name", "cashier")->first();
                if ($cashierRole) {
                    $newUser->assignRole($cashierRole);
                }

                $request->session()->regenerate();
                Auth::login($newUser);
                return redirect()->route("login")->with("success", "Account created. Please log in.");
            } else {
                return back()->with("error", "Something went wrong");
            }
        } else {
            return view("frontend.authentication.sign-up");
        }
    }

    public function forgetPassword(Request $request)
    {
        if ($request->isMethod("post")) {
            $request->validate(["email" => "email|required"]);

            // Rate limit password reset: 3 попытки за 15 минут
            $key = "password-reset:" . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->with(
                    "error",
                    "Too many reset attempts. Please try again in {$seconds} seconds."
                );
            }
            RateLimiter::hit($key, 900);

            $findUser = User::where("email", $request->email)->first();
            $otp      = rand(11111, 99999);

            if ($findUser) {
                ForgetPassword::updateOrCreate(
                    ["user_id" => $findUser->id],
                    ["otp" => $otp, "email" => $findUser->email, "suspend_duration" => now()->addMinutes(5)]
                );

                session(["user_id" => $findUser->id, "reset-email" => $findUser->email]);

                $mailData = [
                    "title" => readConfig("site_name"),
                    "otp"   => $otp,
                    "name"  => $findUser->name,
                ];
                Mail::to($findUser->email)->send(new PasswordReset($mailData));

                return redirect()->route("password.reset")->with("success", "Check your inbox for otp code");
            } else {
                // Не раскрываем, существует ли email (timing attack / email enumeration)
                return redirect()->route("password.reset")->with("success", "If that email exists, you will receive a reset code.");
            }
        } else {
            return view("frontend.authentication.forget-password");
        }
    }

    public function resendOtp()
    {
        $findUser = ForgetPassword::where("user_id", session("user_id"))
            ->where("email", session("reset-email"))
            ->first();

        if ($findUser) {
            $user = User::find(session("user_id"));
            $otp  = rand(11111, 99999);

            $findUser->otp              = $otp;
            $findUser->resent_count++;
            $findUser->suspend_duration = now()->addMinutes(5);
            $findUser->save();

            $mailData = ["title" => readConfig("site_name"), "otp" => $otp, "name" => $user->name];
            Mail::to($findUser->email)->send(new PasswordReset($mailData));

            return back()->with("success", "Otp resent successfully");
        } else {
            return back()->with("error", "Something went wrong");
        }
    }

    public function newPassword(Request $request)
    {
        if ($request->isMethod("post")) {
            $request->validate(["password" => "required|confirmed|min:8"]);

            $user = User::find(session("user_id"));
            if ($user) {
                $user->password = bcrypt($request->password);
                $user->save();
                session()->forget("user_id");
                return redirect()->route("login")->with("success", "Password reset successfully");
            } else {
                return redirect()->route("forget.password")->with("error", "Something went wrong");
            }
        } else {
            return view("frontend.authentication.new-password");
        }
    }

    public function resetPassword(Request $request)
    {
        if ($request->isMethod("post")) {
            $request->validate([
                "number_1" => "required",
                "number_2" => "required",
                "number_3" => "required",
                "number_4" => "required",
                "number_5" => "required",
            ]);
            $otp = $request->number_1 . $request->number_2 . $request->number_3 . $request->number_4 . $request->number_5;

            $record = ForgetPassword::where("email", session("reset-email"))
                ->where("otp", $otp)
                ->first();

            if ($record) {
                // FIX: проверяем срок действия ДО удаления записи
                if (now()->greaterThan(Carbon::parse($record->suspend_duration))) {
                    $record->delete();
                    return redirect()->route("login")->with("error", "Otp expired");
                }
                $record->delete();
                session()->forget("reset-email");
                return redirect()->route("new.password");
            } else {
                return back()->with("error", "Invalid otp");
            }
        } else {
            return view("frontend.authentication.reset");
        }
    }

    public function logout()
    {
        if (auth()->user()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect("/");
        } else {
            return back()->with("error", "You are not logged in");
        }
    }

    public function update(Request $request)
    {
        $user = User::find(auth()->id());

        if (demoUserCheck($user->email)) {
            return back()->with("error", "Cannot update details of demo user");
        }

        $request->validate([
            "name"           => "required",
            "email"          => "required|email|unique:users,email," . $user->id,
            "profile_image"  => ["file", new ValidImageType],
        ]);

        if ($request->name !== $user->name) {
            $user->name = $request->name;
        }
        if ($request->email !== $user->email) {
            $user->email     = $request->email;
            $user->google_id = null;
        }
        if ($request->hasFile("profile_image")) {
            $user->profile_image = $this->fileHandler->fileUploadAndGetPath(
                $request->file("profile_image"), "/public/media/users"
            );
        }

        if ($request->current_password || $request->new_password || $request->confirm_password) {
            $request->validate(["new_password" => "required|min:8|confirmed"]);

            if ($user->is_google_registered) {
                $user->is_google_registered = false;
            } else {
                $request->validate(["current_password" => "required"]);
                if (!Hash::check($request->current_password, $user->password)) {
                    throw ValidationException::withMessages([
                        "current_password" => "The current password is incorrect",
                    ]);
                }
            }
            $user->password = bcrypt($request->new_password);
        }

        $user->save();
        return back()->with("success", "Updated Successfully");
    }

    public function redirectUser()
    {
        if (!Auth::check()) {
            return redirect()->route("login")->with("error", "You are not logged in");
        }
        if (Auth::user()->hasRole("Admin")) {
            return redirect()->route("backend.admin.dashboard");
        }
        Auth::logout();
        return redirect()->route("login")->with("error", "Access denied. This panel is for administrators only.");
    }
}
