@extends('backend.master')
@section('title', 'Ingredient Report')

@section('content')

{{-- Stock Overview --}}
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title"><i class="fas fa-warehouse mr-2"></i> Ingredient Stock Levels</h3>
    <a href="{{ route('backend.admin.ingredients.index') }}" class="btn btn-sm btn-secondary">
      <i class="fas fa-arrow-left"></i> Back to Ingredients
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead style="background:#f8f9fa;">
          <tr>
            <th>#</th>
            <th>Ingredient</th>
            <th>Unit</th>
            <th>Stock</th>
            <th>Cost / Unit</th>
            <th>Stock Value</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ingredients as $i => $ingredient)
          <tr class="{{ $ingredient->stock <= 0 ? 'table-danger' : ($ingredient->stock < 50 ? 'table-warning' : '') }}">
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $ingredient->name }}</strong></td>
            <td>{{ $ingredient->unit }}</td>
            <td>{{ number_format($ingredient->stock, 3) }}</td>
            <td>{{ number_format($ingredient->cost, 2) }} {{ currency()->symbol ?? '' }}</td>
            <td>{{ number_format($ingredient->stock * $ingredient->cost, 2) }} {{ currency()->symbol ?? '' }}</td>
            <td>
              @if($ingredient->stock <= 0)
                <span class="badge bg-danger">Out of Stock</span>
              @elseif($ingredient->stock < 50)
                <span class="badge bg-warning text-dark">Low Stock</span>
              @else
                <span class="badge bg-success">OK</span>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-muted py-3">No ingredients found.</td>
          </tr>
          @endforelse
        </tbody>
        @if($ingredients->isNotEmpty())
        <tfoot style="background:#f8f9fa;">
          <tr>
            <td colspan="5"><strong>Total Inventory Value</strong></td>
            <td colspan="2">
              <strong>
                {{ number_format($ingredients->sum(fn($i) => $i->stock * $i->cost), 2) }}
                {{ currency()->symbol ?? '' }}
              </strong>
            </td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>

{{-- Producibility Report --}}
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-industry mr-2"></i> Product Producibility</h3>
    <small class="text-muted ml-2">Based on current ingredient stock and recipes.</small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead style="background:#f8f9fa;">
          <tr>
            <th>#</th>
            <th>Product</th>
            <th>Can Make (units)</th>
            <th>Blocking Ingredients</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($producibility as $i => $row)
          <tr class="{{ $row['can_make'] == 0 ? 'table-danger' : '' }}">
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $row['product']->name }}</strong></td>
            <td>
              @if($row['can_make'] == 0)
                <span class="badge bg-danger">⛔ Cannot Make</span>
              @elseif($row['can_make'] < 5)
                <span class="badge bg-warning text-dark">{{ $row['can_make'] }} units</span>
              @else
                <span class="badge bg-success">{{ $row['can_make'] }} units</span>
              @endif
            </td>
            <td>
              @if(!empty($row['blocking']))
                <small class="text-danger">{{ implode('; ', $row['blocking']) }}</small>
              @else
                <small class="text-muted">—</small>
              @endif
            </td>
            <td>
              <a href="{{ route('backend.admin.products.recipes', $row['product']->id) }}"
                 class="btn btn-xs btn-outline-primary btn-sm">
                <i class="fas fa-scroll"></i> Recipe
              </a>
              @if($row['can_make'] == 0)
                {{-- show arrival buttons for blocking ingredients --}}
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-3">
              No products with recipes. <a href="{{ route('backend.admin.products.index') }}">Set up recipes</a>.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection

@push('style')
<style>
  .table-danger td { color: #721c24; }
  .table-warning td { color: #856404; }
</style>
@endpush
