@extends('backend.master')
@section('title', 'Рецепт — '.$product->name)

@section('content')
<div class="row">
  {{-- Текущий рецепт --}}
  <div class="col-md-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:#e8724a;color:#fff;">
        <h3 class="card-title mb-0">
          <i class="fas fa-scroll mr-2"></i>
          Рецепт: <strong>{{ $product->name }}</strong>
        </h3>
        <small>1 порция = 1 единица товара</small>
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
                <th>Ингредиент</th>
                <th>Кол-во на 1 ед.</th>
                <th>Ед. изм.</th>
                <th>Действие</th>
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
                        onsubmit="return confirm('Удалить ингредиент из рецепта?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="fas fa-times"></i> Удалить
                    </button>
                  </form>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-3">
                  <i class="fas fa-utensils fa-2x mb-2 d-block"></i>
                  Рецепт пуст. Добавьте ингредиенты справа.
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <a href="{{ route('backend.admin.products.index') }}" class="btn btn-secondary btn-sm">
          <i class="fas fa-arrow-left"></i> К товарам
        </a>
        <a href="{{ route('backend.admin.ingredients.report') }}" class="btn btn-info btn-sm">
          <i class="fas fa-chart-bar"></i> Отчёт склада
        </a>
        <a href="{{ route('backend.admin.inventory.ledger') }}" class="btn btn-dark btn-sm">
          <i class="fas fa-book"></i> Журнал
        </a>
      </div>
    </div>
  </div>

  {{-- Добавить ингредиент --}}
  <div class="col-md-5">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle mr-2" style="color:#e8724a"></i> Добавить ингредиент</h3>
      </div>
      <div class="card-body">
        @error('ingredient_id')<div class="alert alert-danger">{{ $message }}</div>@enderror
        @error('quantity')<div class="alert alert-danger">{{ $message }}</div>@enderror

        @if($ingredients->isEmpty())
          <div class="alert alert-warning">
            Ингредиентов нет.
            <a href="{{ route('backend.admin.ingredients.create') }}">Создать первый</a>.
          </div>
        @else
        <form action="{{ route('backend.admin.products.recipes.store', $product->id) }}" method="POST">
          @csrf

          <div class="form-group">
            <label>Ингредиент <span class="text-danger">*</span></label>
            <select name="ingredient_id" class="form-control select2" required>
              <option value="">— выберите —</option>
              @foreach($ingredients as $ingredient)
              <option value="{{ $ingredient->id }}"
                {{ old('ingredient_id') == $ingredient->id ? 'selected' : '' }}>
                {{ $ingredient->name }}
                ({{ number_format($ingredient->stock, 2) }} {{ $ingredient->unit }} на складе)
              </option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label>Количество на 1 единицу товара <span class="text-danger">*</span></label>
            <input type="number" name="quantity" step="0.001" min="0.001"
                   class="form-control" placeholder="Например: 18.000" required
                   value="{{ old('quantity') }}">
            <small class="text-muted">
              Сколько нужно ингредиента чтобы приготовить <strong>1</strong> порцию {{ $product->name }}?
            </small>
          </div>

          <button type="submit" class="btn btn-block" style="background:#e8724a;color:#fff;">
            <i class="fas fa-save"></i> Сохранить в рецепте
          </button>
        </form>
        @endif

        <div class="alert alert-info mt-3 mb-0">
          <i class="fas fa-info-circle"></i>
          Если ингредиент уже добавлен — количество обновится.
        </div>
      </div>
    </div>

    {{-- Текущий склад --}}
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-warehouse mr-1"></i> Остатки на складе</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Ингредиент</th><th>Остаток</th></tr></thead>
            <tbody>
              @foreach($ingredients as $ingredient)
              <tr class="{{ $ingredient->stock <= 0 ? 'table-danger' : ($ingredient->stock < 50 ? 'table-warning' : '') }}">
                <td>{{ $ingredient->name }}</td>
                <td>
                  @if($ingredient->stock <= 0)
                    <span class="text-danger"><strong>0</strong> {{ $ingredient->unit }}</span>
                  @else
                    {{ number_format($ingredient->stock, 2) }} {{ $ingredient->unit }}
                  @endif
                </td>
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
