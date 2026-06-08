@extends('backend.master')
@section('title', 'Новый ингредиент')

@section('content')
<div class="card" style="max-width:560px;margin:0 auto;">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-plus-circle mr-2"></i> Добавить ингредиент</h3>
  </div>
  <div class="card-body">
    <form action="{{ route('backend.admin.ingredients.store') }}" method="POST">
      @csrf

      <div class="form-group">
        <label>Название <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name') }}" placeholder="Например: Кофе зерно" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="form-group">
        <label>Единица измерения <span class="text-danger">*</span></label>
        <select name="unit" class="form-control @error('unit') is-invalid @enderror" required>
          <option value="">— выберите —</option>
          <option value="g"   {{ old('unit') === 'g'   ? 'selected' : '' }}>г (граммы)</option>
          <option value="ml"  {{ old('unit') === 'ml'  ? 'selected' : '' }}>мл (миллилитры)</option>
          <option value="pcs" {{ old('unit') === 'pcs' ? 'selected' : '' }}>шт (штуки)</option>
        </select>
        @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="form-group">
        <label>Начальный остаток</label>
        <input type="number" name="stock" class="form-control @error('stock') is-invalid @enderror"
               value="{{ old('stock', 0) }}" min="0" step="0.001" placeholder="0">
        <small class="text-muted">Текущее количество на складе.</small>
        @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="form-group">
        <label>Цена за единицу <small class="text-muted">({{ currency()->symbol ?? '' }})</small></label>
        <input type="number" name="cost" class="form-control @error('cost') is-invalid @enderror"
               value="{{ old('cost', 0) }}" min="0" step="0.01" placeholder="0.00">
        @error('cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="d-flex justify-content-between">
        <a href="{{ route('backend.admin.ingredients.index') }}" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Назад
        </a>
        <button type="submit" class="btn" style="background:#e8724a;color:#fff;">
          <i class="fas fa-save"></i> Сохранить
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
