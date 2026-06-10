@extends('backend.master')

@section('title', 'Update Product')

@section('content')
<div class="card">
  <div class="card-body">
    <form action="{{ route('backend.admin.products.update',$product->id) }}" method="post" class="accountForm"
      enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <div class="card-body">
        <div class="row">
          <div class="mb-3 col-md-6">
            <label for="title" class="form-label">
              Name
              <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter title" name="name"
              value="{{ old('name', $product->name) }}" required>
          </div>
          <div class="mb-3 col-md-6">
            <label for="sku" class="form-label">
              Sku
              <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" placeholder="Enter sku" name="sku"
              value="{{ old('sku',$product->sku)}}" required>
          </div>
          <div class="mb-3 col-md-6">
            <label for="brand_id" class="form-label">
              Brand
              <span class="text-danger">*</span>
            </label>
            <select class="form-control select2" style="width: 100%;" name="brand_id" required>
              <option value="">Select Brand</option>
              @foreach ($brands as $item)
              <option value={{ $item->id }}
                {{ $product->brand_id == $item->id ? 'selected' : '' }}>
                {{ $item->name }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="mb-3 col-md-6">
            <label for="category_id" class="form-label">
              Category
              <span class="text-danger">*</span>
            </label>
            <select class="form-control select2" style="width: 100%;" name="category_id" required>
              <option value="">Select Category</option>
              @foreach ($categories as $item)
              <option value={{ $item->id }}
                {{ $product->category_id == $item->id ? 'selected' : '' }}>
                {{ $item->name }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="mb-3 col-md-6">
            <label for="price" class="form-label">
              Price
              <span class="text-danger">*</span>
            </label>
            <input type="number" step="0.01" min="0" class="form-control"
              placeholder="Enter price" name="price" value="{{ old('price',$product->price) }}" required>
          </div>
          <div class="mb-3 col-md-6">
            <label for="unit_id" class="form-label">
              Unit
              <span class="text-danger">*</span>
            </label>
            <select class="form-control" style="width: 100%;" name="unit_id" required>
              <option value="">Select Unit</option>
              @foreach ($units as $item)
              <option value={{ $item->id }}
                {{ $product->unit_id == $item->id ? 'selected' : '' }}>
                {{ $item->title . ' (' . $item->short_name . ')' }}
              </option>
              @endforeach
            </select>
          </div>
          <!-- <div class="mb-3 col-md-6">
          <label for="quantity" class="form-label">
            Initial Stock
            <span class="text-danger">*</span>
          </label>
          <input type="number" class="form-control" placeholder="Enter quantity" name="quantity"
            value="{{ old('quantity',$product->quantity) }}" required>
        </div> -->
          <div class="mb-3 col-md-6">
            <label for="discount_type" class="form-label">
              Discount Type
            </label>
            <select class="form-control form-select" name="discount_type">
              <option value="">Select Discount Type</option>
              <option value="fixed" {{ $product->discount_type == 'fixed' ? 'selected' : '' }}>
                Fixed
              </option>
              <option value="percentage"
                {{ $product->discount_type  == 'percentage' ? 'selected' : '' }}>
                Percentage
              </option>
            </select>
          </div>
          <div class="mb-3 col-md-6">
            <label for="discount_value" class="form-label">
              Discount Amount
            </label>
            <input type="number" step="0.01" min="0" class="form-control"
              placeholder="Enter discount" name="discount" value="{{ old('discount',$product->discount) }}">
          </div>
          <div class="mb-3 col-md-6">
            <label for="purchase_price" class="form-label">
              Purchase Price
              <span class="text-danger">*</span>
            </label>
            <input type="number" step="0.01" min="0" class="form-control"
              placeholder="Enter purchase Price" name="purchase_price" value="{{ old('purchase_price',$product->purchase_price) }}" required>
          </div>
          <div class="mb-3 col-md-6">
            <label for="thumbnailInput" class="form-label">
              Image
            </label>
            <div class="image-upload-container" id="imageUploadContainer">
              <input type="file" class="form-control" name="product_image" id="thumbnailInput" accept="image/*" style="display: none;">
              <div class="thumb-preview" id="thumbPreviewContainer">
                <img src="{{ asset('storage/' . $product->image) }}" alt="Thumbnail Preview"
                  class="img-thumbnail" id="thumbnailPreview" onerror="this.onerror=null; this.src='{{ asset('assets/images/no-image.png') }}'">
                <div class="upload-text d-none">
                  <i class="fas fa-plus-circle"></i>
                  <span>Upload Image</span>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3 col-md-12">
            <label for="description" class="form-label">
              Description
            </label>
            <textarea class="form-control" placeholder="Enter description" name="description">{{ old('description',$product->description) }}</textarea>
          </div>

          <div class="mb-3 col-md-6">
            <label for="expire_date" class="form-label">
              Expire date
            </label>

            <div class="input-group date" id="reservationdate" data-target-input="nearest">
              <input type="text" placeholder="Enter product expire date" class="form-control datetimepicker-input" data-target="#reservationdate" name="expire_date" value="{{ old('expire_date',$product->expire_date) }}" />
              <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
              </div>
            </div>
          </div>
          <div class="mb-3 col-md-12">
            <div class="form-switch px-4">
              <input type="hidden" name="status" value="0">
              <input class="form-check-input" type="checkbox" name="status" id="active"
                value="1" @if($product->status==1) checked @endif>
              <label class="form-check-label" for="active">
                Active
              </label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 d-flex align-items-center gap-2" style="gap:10px;">
            <button type="submit" class="btn bg-gradient-primary">Update</button>
            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#ingredientsModal">
              <i class="fas fa-scroll mr-1"></i> Ингредиенты
              @if($recipes->count())
                <span class="badge badge-light ml-1">{{ $recipes->count() }}</span>
              @endif
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- ── Модальное окно: управление рецептом ── --}}
<div class="modal fade" id="ingredientsModal" tabindex="-1" role="dialog" aria-labelledby="ingredientsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background:#e8724a; color:#fff;">
        <h5 class="modal-title" id="ingredientsModalLabel">
          <i class="fas fa-scroll mr-2"></i>
          Рецепт: <strong>{{ $product->name }}</strong>
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Закрыть">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">

        {{-- Список текущих ингредиентов --}}
        <div class="mb-3">
          <h6 class="font-weight-bold mb-2"><i class="fas fa-list mr-1"></i> Текущий состав рецепта</h6>
          @if($recipes->isEmpty())
            <div class="alert alert-info py-2">
              <i class="fas fa-info-circle mr-1"></i> Рецепт пуст. Добавьте ингредиенты ниже.
            </div>
          @else
            <table class="table table-sm table-bordered mb-0">
              <thead class="thead-light">
                <tr>
                  <th>#</th>
                  <th>Ингредиент</th>
                  <th>Количество на 1 порцию</th>
                  <th>Ед. изм.</th>
                  <th>Удалить</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recipes as $i => $recipe)
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td><strong>{{ $recipe->ingredient->name }}</strong></td>
                  <td>{{ number_format($recipe->quantity, 3) }}</td>
                  <td><span class="badge badge-secondary">{{ $recipe->ingredient->unit }}</span></td>
                  <td>
                    <form action="{{ route('backend.admin.recipes.destroy', $recipe->id) }}"
                          method="POST" style="display:inline;"
                          onsubmit="return confirm('Удалить этот ингредиент из рецепта?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-danger btn-xs">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>

        <hr>

        {{-- Форма добавления ингредиента --}}
        <h6 class="font-weight-bold mb-2"><i class="fas fa-plus-circle mr-1" style="color:#e8724a"></i> Добавить / изменить ингредиент</h6>

        @if($ingredients->isEmpty())
          <div class="alert alert-warning py-2">
            Ингредиентов нет.
            <a href="{{ route('backend.admin.ingredients.create') }}" target="_blank">Создать первый</a>.
          </div>
        @else
          <form action="{{ route('backend.admin.products.recipes.store', $product->id) }}" method="POST">
            @csrf
            <div class="form-row align-items-end">
              <div class="form-group col-md-6 mb-2">
                <label class="font-weight-bold">Ингредиент <span class="text-danger">*</span></label>
                <select name="ingredient_id" class="form-control" required>
                  <option value="">— выберите —</option>
                  @foreach($ingredients as $ingredient)
                  <option value="{{ $ingredient->id }}">
                    {{ $ingredient->name }}
                    ({{ number_format($ingredient->stock, 2) }} {{ $ingredient->unit }} на складе)
                  </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group col-md-4 mb-2">
                <label class="font-weight-bold">Кол-во на 1 ед. <span class="text-danger">*</span></label>
                <input type="number" name="quantity" step="0.001" min="0.001"
                       class="form-control" placeholder="Например: 200.000" required>
                <small class="text-muted">Единица измерения — как у ингредиента</small>
              </div>
              <div class="form-group col-md-2 mb-2">
                <button type="submit" class="btn btn-block" style="background:#e8724a;color:#fff;">
                  <i class="fas fa-save"></i> Добавить
                </button>
              </div>
            </div>
            <small class="text-muted">
              <i class="fas fa-info-circle"></i>
              Если ингредиент уже в рецепте — количество обновится.
            </small>
          </form>
        @endif
      </div>
      <div class="modal-footer justify-content-between">
        <a href="{{ route('backend.admin.products.recipes', $product->id) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
          <i class="fas fa-external-link-alt"></i> Открыть полную страницу
        </a>
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Закрыть</button>
      </div>
    </div>
  </div>
</div>

@endsection
@push('script')
<script src="{{ asset('js/image-field.js') }}"></script>

<script>
  $(function() {
    //Date picker
    $('#reservationdate').datetimepicker({
      format: 'YYYY-MM-DD'
    });
  })
</script>
@endpush