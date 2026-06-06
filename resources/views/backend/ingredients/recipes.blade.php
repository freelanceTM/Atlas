@extends('backend.master')
@section('title', 'Recipe — '.$product->name)

@section('content')

<div class="row">
  {{-- Current Recipe --}}
  <div class="col-md-7">
    <div class="card">
      <div class="card-header" style="background:#e8724a;color:#fff;">
        <h3 class="card-title">
          <i class="fas fa-scroll mr-2"></i>
          Recipe for: <strong>{{ $product->name }}</strong>
        </h3>
      </div>
      <div class="card-body p-0">

        @if(session('success'))
          <div class="alert alert-success m-3">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead style="background:#f8f9fa;">
              <tr>
                <th>#</th>
                <th>Ingredient</th>
                <th>Qty per 1 unit</th>
                <th>Unit</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recipes as $i => $recipe)
              <tr>
                <td>{{ $i + 1 }}</td>
                <td><strong>{{ $recipe->ingredient->name }}</strong></td>
                <td>{{ number_format($recipe->quantity, 3) }}</td>
                <td><span class="badge bg-secondary">{{ $recipe->ingredient->unit }}</span></td>
                <td>
                  <form action="{{ route('backend.admin.recipes.destroy', $recipe->id) }}"
                        method="POST" style="display:inline;"
                        onsubmit="return confirm('Remove this ingredient from recipe?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="fas fa-times"></i> Remove
                    </button>
                  </form>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-3">
                  No ingredients in recipe yet. Add one on the right.
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

      </div>
      <div class="card-footer">
        <a href="{{ route('backend.admin.products.index') }}" class="btn btn-secondary btn-sm">
          <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        <a href="{{ route('backend.admin.ingredients.report') }}" class="btn btn-info btn-sm ml-2">
          <i class="fas fa-chart-bar"></i> View Report
        </a>
      </div>
    </div>
  </div>

  {{-- Add Ingredient to Recipe --}}
  <div class="col-md-5">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle mr-2"></i> Add Ingredient</h3>
      </div>
      <div class="card-body">
        @if($ingredients->isEmpty())
          <div class="alert alert-warning">
            No ingredients available.
            <a href="{{ route('backend.admin.ingredients.create') }}">Create one first</a>.
          </div>
        @else
        <form action="{{ route('backend.admin.products.recipes.store', $product->id) }}" method="POST">
          @csrf

          <div class="form-group">
            <label>Ingredient <span class="text-danger">*</span></label>
            <select name="ingredient_id" class="form-control @error('ingredient_id') is-invalid @enderror" required>
              <option value="">— select ingredient —</option>
              @foreach($ingredients as $ingredient)
              <option value="{{ $ingredient->id }}"
                {{ old('ingredient_id') == $ingredient->id ? 'selected' : '' }}>
                {{ $ingredient->name }} ({{ $ingredient->unit }})
              </option>
              @endforeach
            </select>
            @error('ingredient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="form-group">
            <label>Quantity per 1 unit of product <span class="text-danger">*</span></label>
            <input type="number" name="quantity"
                   class="form-control @error('quantity') is-invalid @enderror"
                   min="0.001" step="0.001" placeholder="e.g. 18.000" required>
            <small class="text-muted">
              How much of this ingredient is needed to produce <strong>1</strong> {{ $product->name }}?
            </small>
            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <button type="submit" class="btn btn-block" style="background:#e8724a;color:#fff;">
            <i class="fas fa-save"></i> Add / Update in Recipe
          </button>
        </form>
        @endif

        <hr>
        <div class="callout callout-info">
          <p class="mb-0">
            <i class="fas fa-info-circle"></i>
            If this product already has the selected ingredient, the quantity will be updated.
          </p>
        </div>
      </div>
    </div>

    {{-- Current stock card --}}
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-warehouse mr-1"></i> Current Ingredient Stock</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Ingredient</th><th>Stock</th></tr></thead>
            <tbody>
              @foreach($ingredients as $ingredient)
              <tr class="{{ $ingredient->stock <= 0 ? 'table-danger' : '' }}">
                <td>{{ $ingredient->name }}</td>
                <td>{{ number_format($ingredient->stock, 2) }} {{ $ingredient->unit }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

@endsection
