<?php

namespace App\Http\Controllers\Backend\Ingredient;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngredientController extends Controller
{
    // ─────────────────────────────────────────────
    // INGREDIENT CRUD
    // ─────────────────────────────────────────────

    public function index()
    {
        $ingredients = Ingredient::orderBy('name')->get();
        return view('backend.ingredients.index', compact('ingredients'));
    }

    public function create()
    {
        return view('backend.ingredients.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255|unique:ingredients,name',
            'unit'  => 'required|in:g,ml,pcs',
            'stock' => 'nullable|numeric|min:0',
            'cost'  => 'nullable|numeric|min:0',
        ]);

        Ingredient::create([
            'name'  => $data['name'],
            'unit'  => $data['unit'],
            'stock' => $data['stock'] ?? 0,
            'cost'  => $data['cost'] ?? 0,
        ]);

        return redirect()->route('backend.admin.ingredients.index')
            ->with('success', 'Ingredient created successfully.');
    }

    public function edit($id)
    {
        $ingredient = Ingredient::findOrFail($id);
        return view('backend.ingredients.edit', compact('ingredient'));
    }

    public function update(Request $request, $id)
    {
        $ingredient = Ingredient::findOrFail($id);

        $data = $request->validate([
            'name'  => 'required|string|max:255|unique:ingredients,name,' . $ingredient->id,
            'unit'  => 'required|in:g,ml,pcs',
            'cost'  => 'nullable|numeric|min:0',
        ]);

        $ingredient->update([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'cost' => $data['cost'] ?? 0,
        ]);

        return redirect()->route('backend.admin.ingredients.index')
            ->with('success', 'Ingredient updated successfully.');
    }

    public function destroy($id)
    {
        $ingredient = Ingredient::findOrFail($id);

        if ($ingredient->recipes()->exists()) {
            return redirect()->route('backend.admin.ingredients.index')
                ->with('error', 'Cannot delete: ingredient is used in one or more recipes.');
        }

        $ingredient->delete();

        return redirect()->route('backend.admin.ingredients.index')
            ->with('success', 'Ingredient deleted.');
    }

    // ─────────────────────────────────────────────
    // STOCK ARRIVAL
    // ─────────────────────────────────────────────

    public function arrival(Request $request, $id)
    {
        $ingredient = Ingredient::findOrFail($id);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'quantity' => 'required|numeric|min:0.001',
                'cost'     => 'nullable|numeric|min:0',
            ]);

            DB::transaction(function () use ($ingredient, $data) {
                Ingredient::where('id', $ingredient->id)->lockForUpdate()->first();
                Ingredient::where('id', $ingredient->id)->increment('stock', $data['quantity']);

                if (!empty($data['cost'])) {
                    $ingredient->cost = $data['cost'];
                    $ingredient->save();
                }
            });

            return redirect()->route('backend.admin.ingredients.index')
                ->with('success', number_format($data['quantity'], 3) . ' ' . $ingredient->unit . ' of "' . $ingredient->name . '" added to stock.');
        }

        return view('backend.ingredients.arrival', compact('ingredient'));
    }

    // ─────────────────────────────────────────────
    // REPORT
    // ─────────────────────────────────────────────

    public function report()
    {
        $ingredients = Ingredient::orderBy('name')->get();

        // Products with recipes: calculate how many can be made
        $products = Product::with(['recipes.ingredient'])->get();

        $producibility = [];
        foreach ($products as $product) {
            if ($product->recipes->isEmpty()) {
                continue;
            }

            $canMake = PHP_INT_MAX;
            $blocking = [];

            foreach ($product->recipes as $recipe) {
                $ingredient = $recipe->ingredient;
                if (!$ingredient) {
                    $canMake = 0;
                    $blocking[] = 'missing ingredient';
                    continue;
                }

                if ($recipe->quantity <= 0) {
                    continue;
                }

                if ($ingredient->stock < $recipe->quantity) {
                    $canMake = 0;
                    $blocking[] = $ingredient->name . ' (need ' . $recipe->quantity . ' ' . $ingredient->unit . ', have ' . number_format($ingredient->stock, 3) . ')';
                } else {
                    $possible = floor($ingredient->stock / $recipe->quantity);
                    $canMake = min($canMake, $possible);
                }
            }

            if ($canMake === PHP_INT_MAX) {
                $canMake = 0;
            }

            $producibility[] = [
                'product'  => $product,
                'can_make' => $canMake,
                'blocking' => $blocking,
            ];
        }

        // Sort: can't make first, then by quantity ascending
        usort($producibility, fn($a, $b) => $a['can_make'] <=> $b['can_make']);

        return view('backend.ingredients.report', compact('ingredients', 'producibility'));
    }

    // ─────────────────────────────────────────────
    // RECIPE MANAGEMENT
    // ─────────────────────────────────────────────

    public function recipes($productId)
    {
        $product     = Product::findOrFail($productId);
        $recipes     = Recipe::with('ingredient')->where('product_id', $productId)->get();
        $ingredients = Ingredient::orderBy('name')->get();

        return view('backend.ingredients.recipes', compact('product', 'recipes', 'ingredients'));
    }

    public function storeRecipe(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $data = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity'      => 'required|numeric|min:0.001',
        ]);

        Recipe::updateOrCreate(
            ['product_id' => $product->id, 'ingredient_id' => $data['ingredient_id']],
            ['quantity'   => $data['quantity']]
        );

        return redirect()->route('backend.admin.products.recipes', $product->id)
            ->with('success', 'Recipe updated.');
    }

    public function destroyRecipe($recipeId)
    {
        $recipe = Recipe::findOrFail($recipeId);
        $productId = $recipe->product_id;
        $recipe->delete();

        return redirect()->route('backend.admin.products.recipes', $productId)
            ->with('success', 'Ingredient removed from recipe.');
    }
}
