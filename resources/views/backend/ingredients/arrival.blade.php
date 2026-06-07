@extends('backend.master')
@section('title', 'Stock Arrival — '.$ingredient->name)

@section('content')
<div class="card" style="max-width:520px;margin:0 auto;">
  <div class="card-header" style="background:#e8724a;color:#fff;">
    <h3 class="card-title">
      <i class="fas fa-truck mr-2"></i>
      Stock Arrival: <strong>{{ $ingredient->name }}</strong>
    </h3>
  </div>
  <div class="card-body">

    <div class="info-box mb-3" style="border-left:4px solid #e8724a;">
      <div class="info-box-content">
        <span class="info-box-text">Current Stock</span>
        <span class="info-box-number">
          {{ number_format($ingredient->stock, 3) }} {{ $ingredient->unit }}
        </span>
      </div>
    </div>

    <form action="{{ route('backend.admin.ingredients.arrival', $ingredient->id) }}" method="POST">
      @csrf{{-- FIX BUG-6: removed redundant @method('POST') — POST forms don't need method spoofing --}}

      <div class="form-group">
        <label>Quantity to Add <span class="text-danger">*</span>
          <small class="text-muted">({{ $ingredient->unit }})</small>
        </label>
        <input type="number" name="quantity"
               class="form-control form-control-lg @error('quantity') is-invalid @enderror"
               min="0.001" step="0.001" placeholder="0.000" required autofocus>
        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="form-group">
        <label>Update Cost per Unit
          <small class="text-muted">({{ currency()->symbol ?? '' }}/{{ $ingredient->unit }}, current: {{ number_format($ingredient->cost, 2) }})</small>
        </label>
        <input type="number" name="cost" class="form-control"
               min="0" step="0.01" placeholder="Leave blank to keep current cost"
               value="{{ old('cost') }}">
      </div>

      <div class="d-flex justify-content-between">
        <a href="{{ route('backend.admin.ingredients.index') }}" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back
        </a>
        <button type="submit" class="btn btn-success">
          <i class="fas fa-plus-circle"></i> Add to Stock
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
