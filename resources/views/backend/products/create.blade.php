@extends('backend.master')
@section('title', 'Добавить товар')
@section('content')
<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-7">
    <div class="card atlas-form-card">
      <div class="atlas-form-head">
        <a href="{{ route('backend.admin.products.index') }}" class="atlas-back-btn">
          <i class="fas fa-arrow-left"></i>
        </a>
        <div>
          <h5 class="atlas-form-title">Новый товар</h5>
          <p class="atlas-form-sub">Заполните основную информацию</p>
        </div>
      </div>
      <div class="card-body atlas-form-body">
        <form action="{{ route('backend.admin.products.store') }}" method="post" enctype="multipart/form-data" class="accountForm">
          @csrf

          {{-- Name + SKU --}}
          <div class="atlas-form-row">
            <div class="atlas-form-group">
              <label class="atlas-label">Название <span class="req">*</span></label>
              <input type="text" class="atlas-input" placeholder="Например: Капучино 250мл" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="atlas-form-group">
              <label class="atlas-label">Артикул (SKU) <span class="req">*</span></label>
              <input type="text" class="atlas-input" placeholder="CAFF-001" name="sku" value="{{ old('sku') }}" required>
            </div>
          </div>

          {{-- Category + Brand --}}
          <div class="atlas-form-row">
            <div class="atlas-form-group">
              <label class="atlas-label">Категория <span class="req">*</span></label>
              <select class="atlas-input select2" name="category_id" required>
                <option value="">Выберите категорию</option>
                @foreach($categories as $item)
                  <option value="{{ $item->id }}" {{ old('category_id') == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="atlas-form-group">
              <label class="atlas-label">Бренд <span class="req">*</span></label>
              <select class="atlas-input select2" name="brand_id" required>
                <option value="">Выберите бренд</option>
                @foreach($brands as $item)
                  <option value="{{ $item->id }}" {{ old('brand_id') == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          {{-- Price + Purchase Price --}}
          <div class="atlas-form-row">
            <div class="atlas-form-group">
              <label class="atlas-label">Цена продажи {{ currency()->symbol??'₽' }} <span class="req">*</span></label>
              <input type="number" step="0.01" min="0" class="atlas-input" placeholder="0.00" name="price" value="{{ old('price') }}" required>
            </div>
            <div class="atlas-form-group">
              <label class="atlas-label">Закупочная цена {{ currency()->symbol??'₽' }} <span class="req">*</span></label>
              <input type="number" step="0.01" min="0" class="atlas-input" placeholder="0.00" name="purchase_price" value="{{ old('purchase_price') }}" required>
            </div>
          </div>

          {{-- Discount + Unit --}}
          <div class="atlas-form-row">
            <div class="atlas-form-group">
              <label class="atlas-label">Тип скидки</label>
              <select class="atlas-input" name="discount_type">
                <option value="">Без скидки</option>
                <option value="fixed" {{ old('discount_type') == 'fixed' ? 'selected' : '' }}>Фиксированная</option>
                <option value="percentage" {{ old('discount_type') == 'percentage' ? 'selected' : '' }}>Процент</option>
              </select>
            </div>
            <div class="atlas-form-group">
              <label class="atlas-label">Размер скидки</label>
              <input type="number" step="0.01" min="0" class="atlas-input" placeholder="0" name="discount" value="{{ old('discount') }}">
            </div>
          </div>

          {{-- Unit + Expire --}}
          <div class="atlas-form-row">
            <div class="atlas-form-group">
              <label class="atlas-label">Единица измерения <span class="req">*</span></label>
              <select class="atlas-input" name="unit_id" required>
                <option value="">Выберите единицу</option>
                @foreach($units as $item)
                  <option value="{{ $item->id }}" {{ old('unit_id') == $item->id ? 'selected' : '' }}>{{ $item->title }} ({{ $item->short_name }})</option>
                @endforeach
              </select>
            </div>
            <div class="atlas-form-group">
              <label class="atlas-label">Срок годности</label>
              <div class="input-group date" id="reservationdate" data-target-input="nearest">
                <input type="text" placeholder="дд.мм.гггг" class="atlas-input datetimepicker-input" data-target="#reservationdate" name="expire_date" value="{{ old('expire_date') }}" />
                <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                  <div class="input-group-text" style="background:var(--dk-surface3);border:1.5px solid var(--dk-border);border-left:none">
                    <i class="fa fa-calendar" style="color:var(--dk-text-muted)"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {{-- Image + Description --}}
          <div class="atlas-form-row">
            <div class="atlas-form-group">
              <label class="atlas-label">Фото товара</label>
              <div class="image-upload-container" id="imageUploadContainer">
                <input type="file" class="form-control" name="product_image" id="thumbnailInput" accept="image/*" style="display:none">
                <div class="thumb-preview" id="thumbPreviewContainer" style="min-height:100px;display:flex;align-items:center;justify-content:center;background:var(--dk-surface3);border:2px dashed var(--dk-border);border-radius:10px;cursor:pointer">
                  <img src="{{ asset('backend/assets/images/blank.png') }}" alt="" class="img-thumbnail d-none" id="thumbnailPreview" style="max-height:120px">
                  <div class="upload-text" style="text-align:center;color:var(--dk-text-muted)">
                    <i class="fas fa-plus-circle" style="font-size:24px;display:block;margin-bottom:6px"></i>
                    <span style="font-size:13px">Загрузить фото</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="atlas-form-group">
              <label class="atlas-label">Описание</label>
              <textarea class="atlas-input" placeholder="Краткое описание товара..." name="description" rows="4" style="resize:vertical">{{ old('description') }}</textarea>
            </div>
          </div>

          {{-- Active toggle --}}
          <div class="atlas-form-group">
            <label class="atlas-toggle-wrap">
              <input type="hidden" name="status" value="0">
              <input class="atlas-toggle-input" type="checkbox" name="status" id="active" value="1" checked>
              <span class="atlas-toggle-slider"></span>
              <span class="atlas-toggle-label">Активный товар (виден на кассе)</span>
            </label>
          </div>

          {{-- Submit --}}
          <div class="atlas-form-actions">
            <a href="{{ route('backend.admin.products.index') }}" class="btn atlas-btn-secondary">Отмена</a>
            <button type="submit" class="btn atlas-btn-primary">
              <i class="fas fa-save mr-1"></i> Создать товар
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@push('style')
<style>
.atlas-form-card { border-radius:16px; overflow:hidden; }
.atlas-form-head { display:flex; align-items:center; gap:14px; padding:20px 24px 0; }
.atlas-back-btn { width:38px; height:38px; border-radius:9px; background:var(--dk-surface2); border:1.5px solid var(--dk-border); color:var(--dk-text-muted); display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all .15s; flex-shrink:0; }
.atlas-back-btn:hover { border-color:#5b7cfa; color:#5b7cfa; background:rgba(91,124,250,.1); }
.atlas-form-title { font-size:17px; font-weight:700; margin:0 0 2px; }
.atlas-form-sub { font-size:12px; color:var(--dk-text-muted); margin:0; }
.atlas-form-body { padding:20px 24px 24px; }
.atlas-form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
@media(max-width:600px){.atlas-form-row{grid-template-columns:1fr}}
.atlas-form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:0; }
.atlas-label { font-size:12px; font-weight:600; color:var(--dk-text-muted); text-transform:uppercase; letter-spacing:.6px; }
.req { color:#ff5470; }
.atlas-input {
  background:var(--dk-surface2) !important; border:1.5px solid var(--dk-border) !important;
  border-radius:9px !important; color:var(--dk-text) !important; padding:10px 14px !important;
  font-size:14px !important; font-family:inherit !important; transition:border-color .15s, box-shadow .15s !important;
  width:100%;
}
.atlas-input:focus { border-color:#5b7cfa !important; box-shadow:0 0 0 3px rgba(91,124,250,.15) !important; outline:none !important; }
.atlas-input::placeholder { color:var(--dk-text-muted) !important; }
.atlas-form-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:24px; padding-top:20px; border-top:1px solid var(--dk-border); }
.atlas-btn-primary { background:#5b7cfa; color:#fff; border:none; border-radius:9px; padding:10px 22px; font-size:13px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .15s; cursor:pointer; }
.atlas-btn-primary:hover { background:#3d5ce8; color:#fff; transform:translateY(-1px); box-shadow:0 4px 14px rgba(91,124,250,.4); }
.atlas-btn-secondary { background:var(--dk-surface2); color:var(--dk-text-muted); border:1.5px solid var(--dk-border); border-radius:9px; padding:10px 20px; font-size:13px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; transition:all .15s; cursor:pointer; }
.atlas-btn-secondary:hover { border-color:#5b7cfa; color:#5b7cfa; }
.atlas-toggle-wrap { display:flex; align-items:center; gap:12px; cursor:pointer; user-select:none; }
.atlas-toggle-input { display:none; }
.atlas-toggle-slider { width:44px; height:24px; border-radius:12px; background:var(--dk-surface3); border:1.5px solid var(--dk-border); position:relative; flex-shrink:0; transition:all .2s; }
.atlas-toggle-slider::after { content:''; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:var(--dk-text-muted); transition:all .2s; }
.atlas-toggle-input:checked + .atlas-toggle-slider { background:#5b7cfa; border-color:#5b7cfa; }
.atlas-toggle-input:checked + .atlas-toggle-slider::after { left:21px; background:#fff; }
.atlas-toggle-label { font-size:13px; font-weight:500; color:var(--dk-text); }
.select2-container--default .select2-selection--single { height:auto !important; padding:2px 0 !important; }
</style>
@endpush

@push('script')
<script src="{{ asset('js/image-field.js') }}"></script>
<script>
$(function() {
  $('#reservationdate').datetimepicker({ format:'YYYY-MM-DD' });
  $('.atlas-toggle-wrap').on('click', function(e) {
    if ($(e.target).is('input')) return;
    const cb = $(this).find('.atlas-toggle-input');
    cb.prop('checked', !cb.prop('checked'));
  });
});
</script>
@endpush
