<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderTransaction;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::get();
        $data = [
            'sub_total'       => $orders->sum('sub_total'),
            'discount'        => $orders->sum('discount'),
            'total'           => $orders->sum('total'),
            'paid'            => $orders->sum('paid'),
            'due'             => $orders->sum('due'),
            'total_customer'  => Customer::count(),
            'total_order'     => $orders->count(),
            'total_product'   => Product::count(),
            'total_sale_item' => OrderProduct::sum('quantity'),
        ];

        $startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $endDate   = Carbon::now()->format('Y-m-d');
        if ($request->has('daterange')) {
            $dates = explode(' to ', $request->query('daterange'));
            if (count($dates) == 2) {
                $startDate = Carbon::parse($dates[0])->format('Y-m-d');
                $endDate   = Carbon::parse($dates[1])->format('Y-m-d');
            }
        }

        // FIX: DATE() is standard SQL — works on SQLite and MySQL
        $dailyTotals = OrderTransaction::selectRaw('DATE(created_at) as date, SUM(amount) as total_amount')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get();

        $data['dates']       = $dailyTotals->pluck('date')->toArray();
        $data['totalAmounts'] = $dailyTotals->pluck('total_amount')->toArray();
        $data['dateRange']   = 'from ' . $startDate . ' to ' . $endDate;

        $currentYear = now()->year;
        $data['currentYear'] = $currentYear;

        // FIX: replaced DATE_FORMAT (MySQL-only) with PHP/Carbon grouping —
        // fetch the year's transactions, then group by Y-m in PHP (works on any DB)
        $transactions = OrderTransaction::whereYear('created_at', $currentYear)->get();

        $salesData = $transactions->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m');
        })->map(fn($group) => $group->sum('amount'));

        $tempMonths          = [];
        $tempTotalAmountMonth = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthKey              = Carbon::create($currentYear, $i, 1)->format('Y-m');
            $tempMonths[]          = $monthKey;
            $tempTotalAmountMonth[] = $salesData[$monthKey] ?? 0;
        }

        $data['months']           = $tempMonths;
        $data['totalAmountMonth'] = $tempTotalAmountMonth;

        return view('backend.index', $data);
    }

    public function profile()
    {
        $user = auth()->user();
        return view('backend.profile.index', compact('user'));
    }
}
