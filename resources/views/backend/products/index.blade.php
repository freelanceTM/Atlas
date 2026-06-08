@extends('backend.master')
@section('title', 'Товары')
@section('content')
<div class="card atlas-card">
  <div class="atlas-card-head">
    <div>
      <h5 class="atlas-card-title"><i class="fas fa-hamburger mr-2"></i>Меню / Товары</h5>
      <p class="atlas-card-sub">Управление позициями меню</p>
    </div>
    @can('product_create')
    <a href="{{ route('backend.admin.products.create') }}" class="btn atlas-btn-primary">
      <i class="fas fa-plus mr-1"></i> Добавить товар
    </a>
    @endcan
  </div>
  <div class="card-body p-0 mt-3">
    <div class="table-responsive">
      <table id="datatables" class="table table-hover">
        <thead>
          <tr>
            <th data-orderable="false">#</th>
            <th data-orderable="false">Фото</th>
            <th>Название</th>
            <th>Цена {{ currency()->symbol??'₽' }}</th>
            <th>Остаток</th>
            <th>Дата</th>
            <th>Статус</th>
            <th data-orderable="false">Действия</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>
@endsection

@push('style')
<style>
.atlas-card { border-radius:14px; overflow:hidden; }
.atlas-card-head { display:flex; justify-content:space-between; align-items:flex-start; padding:20px 20px 0; flex-wrap:wrap; gap:12px; }
.atlas-card-title { font-size:16px; font-weight:700; margin:0 0 2px; }
.atlas-card-title i { color:#5b7cfa; }
.atlas-card-sub { font-size:12px; color:var(--dk-text-muted); margin:0; }
.atlas-btn-primary { background:#5b7cfa; color:#fff; border:none; border-radius:9px; padding:8px 18px; font-size:13px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .15s; }
.atlas-btn-primary:hover { background:#3d5ce8; color:#fff; text-decoration:none; transform:translateY(-1px); box-shadow:0 4px 14px rgba(91,124,250,.4); }
</style>
@endpush

@push('script')
<script>
$(function() {
  $('#datatables').DataTable({
    processing: true, serverSide: true, ordering: true,
    language: {
      search:'Поиск:', lengthMenu:'Показать _MENU_ записей',
      info:'Записи _START_–_END_ из _TOTAL_', infoEmpty:'Нет записей',
      zeroRecords:'Ничего не найдено', paginate:{first:'«',last:'»',next:'›',previous:'‹'},
      processing:'Загрузка...', emptyTable:'Товаров пока нет'
    },
    ajax: { url:"{{ route('backend.admin.products.index') }}" },
    columns: [
      {data:'DT_RowIndex',name:'DT_RowIndex'},
      {data:'image',name:'image'},
      {data:'name',name:'name'},
      {data:'price',name:'price'},
      {data:'quantity',name:'quantity'},
      {data:'created_at',name:'created_at'},
      {data:'status',name:'status'},
      {data:'action',name:'action'}
    ]
  });
});
</script>
@endpush
