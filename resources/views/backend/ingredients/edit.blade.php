@extends('backend.master')
@section('title', 'Edit Ingredient')

@section('content')
<div class="card" style="max-width:560px;margin:0 auto;">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-edit mr-2"></i> Edit Ingredient</h3>
  </div>
  <div class="card-body">
    <form action="{{ route('backend.admin.ingredients.update', $ingredient->id) }}" method="POST">
      @csrf @method('PUT')

      <div class="form-group">
        <label>Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $ingredient->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="form-group">
        <label>Unit <span class="text-danger">*</span></label>
        <select name="unit" class="form-control @error('unit') is-invalid @enderror" required>
          <option value="g"   {{ $ingredient->unit === 'g'   ? 'selected' : '' }}>g (grams)</option>
          <option value="ml"  {{ $ingredient->unit === 'ml'  ? 'selected' : '' }}>ml (millilitres)</option>
          <option value="pcs" {{ $ingredient->unit === 'pcs' ? 'selected' : '' }}>pcs (pieces)</option>
        </select>
        @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="form-group">
        <label>Current Stock</label>
        <input type="text" class="form-control" value="{{ number_format($ingredient->stock, 3) }} {{ $ingredient->unit }}" disabled>
        <small class="text-muted">
          Use <a href="{{ route('backend.admin.ingredients.arrival', $ingredient->id) }}">Stock Arrival</a> to add stock.
        </small>
      </div>

      <div class="form-group">
        <label>Cost per Unit <small class="text-muted">({{ currency()->symbol ?? '' }})</small></label>
        <input type="number" name="cost" class="form-control @error('cost') is-invalid @enderror"
               value="{{ old('cost', $ingredient->cost) }}" min="0" step="0.01">
        @error('cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="d-flex justify-content-between">
        <a href="{{ route('backend.admin.ingredients.index') }}" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Update
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
