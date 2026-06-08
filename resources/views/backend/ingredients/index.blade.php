@extends('backend.master')
@section('title', 'Ingredients')

@section('content')
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title"><i class="fas fa-flask mr-2"></i> Склад ингредиентов</h3>
    <div>
      <a href="{{ route('backend.admin.ingredients.report') }}" class="btn btn-info btn-sm mr-1">
        <i class="fas fa-chart-bar"></i> Отчёт
      </a>
      <a href="{{ route('backend.admin.ingredients.create') }}" class="btn btn-sm" style="background:#e8724a;color:#fff;">
        <i class="fas fa-plus-circle"></i> Добавить
      </a>
    </div>
  </div>
  <div class="card-body p-0">

    @if(session('success'))
      <div class="alert alert-success mx-3 mt-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger mx-3 mt-3">{{ session('error') }}</div>
    @endif

    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead style="background:#f8f9fa;">
          <tr>
            <th>#</th>
            <th>Название</th>
            <th>Ед. изм.</th>
            <th>Остаток</th>
            <th>Цена / ед.</th>
            <th>Сумма</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ingredients as $i => $ingredient)
          <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $ingredient->name }}</strong></td>
            <td><span class="badge bg-secondary">{{ $ingredient->unit }}</span></td>
            <td>
              @if($ingredient->stock <= 0)
                <span class="badge bg-danger">0 {{ $ingredient->unit }}</span>
              @elseif($ingredient->stock < 50)
                <span class="badge bg-warning text-dark">{{ number_format($ingredient->stock, 2) }} {{ $ingredient->unit }}</span>
              @else
                <span class="badge bg-success">{{ number_format($ingredient->stock, 2) }} {{ $ingredient->unit }}</span>
              @endif
            </td>
            <td>{{ number_format($ingredient->cost, 2) }} {{ currency()->symbol ?? '' }}</td>
            <td>{{ number_format($ingredient->stock * $ingredient->cost, 2) }} {{ currency()->symbol ?? '' }}</td>
            <td>
              <a href="{{ route('backend.admin.ingredients.arrival', $ingredient->id) }}"
                 class="btn btn-sm btn-success" title="Add Stock">
                <i class="fas fa-plus"></i> Приход
              </a>
              <a href="{{ route('backend.admin.ingredients.edit', $ingredient->id) }}"
                 class="btn btn-sm btn-primary" title="Edit">
                <i class="fas fa-edit"></i>
              </a>
              <form action="{{ route('backend.admin.ingredients.destroy', $ingredient->id) }}"
                    method="POST" style="display:inline;"
                    onsubmit="return confirm('Удалить ингредиент?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-muted py-4">
              No ingredients yet. <a href="{{ route('backend.admin.ingredients.create') }}">Add the first one</a>.
            </td>
          </tr>
          @endforelse
        </tbody>
        @if($ingredients->isNotEmpty())
        <tfoot style="background:#f8f9fa;">
          <tr>
            <td colspan="5"><strong>Total Stock Value:</strong></td>
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
@endsection
