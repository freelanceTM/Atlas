@extends('backend.master')
@section('title', 'Заказы')
@section('content')
<div class="card atlas-card">
  <div class="atlas-card-head">
    <div>
      <h5 class="atlas-card-title"><i class="fas fa-receipt mr-2"></i>Заказы</h5>
      <p class="atlas-card-sub">Все заказы системы</p>
    </div>
    <div class="d-flex gap-2 align-items-center" style="gap:8px">
      <span id="orders-count" class="atlas-badge"></span>
    </div>
  </div>
  <div class="atlas-filter-bar">
    <div class="atlas-filter-group">
      <button class="atlas-filter-btn active" data-status="">Все</button>
      <button class="atlas-filter-btn" data-status="paid">Оплачен</button>
      <button class="atlas-filter-btn" data-status="due">Долг</button>
    </div>
    <div class="atlas-filter-group">
      <button class="atlas-type-btn active" data-type="">Все типы</button>
      <button class="atlas-type-btn" data-type="dine_in">🍽 В зале</button>
      <button class="atlas-type-btn" data-type="takeaway">🥡 На вынос</button>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="datatables" class="table table-hover">
        <thead>
          <tr>
            <th data-orderable="false">#</th>
            <th>№ Заказа</th>
            <th>Клиент</th>
            <th>Позиций</th>
            <th>Тип</th>
            <th>Сумма {{ currency()->symbol??'₽' }}</th>
            <th>Скидка {{ currency()->symbol??'₽' }}</th>
            <th>Итого {{ currency()->symbol??'₽' }}</th>
            <th>Оплата {{ currency()->symbol??'₽' }}</th>
            <th>Долг {{ currency()->symbol??'₽' }}</th>
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
.atlas-card-head { display:flex; justify-content:space-between; align-items:flex-start; padding:20px 20px 0; }
.atlas-card-title { font-size:16px; font-weight:700; margin:0 0 2px; }
.atlas-card-title i { color:#5b7cfa; }
.atlas-card-sub { font-size:12px; color:var(--dk-text-muted); margin:0; }
.atlas-badge { background:rgba(91,124,250,.15); color:#5b7cfa; border:1px solid rgba(91,124,250,.3); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:600; }
.atlas-filter-bar { display:flex; gap:12px; flex-wrap:wrap; padding:16px 20px; border-bottom:1px solid var(--dk-border); }
.atlas-filter-group { display:flex; gap:4px; }
.atlas-filter-btn, .atlas-type-btn {
  padding:6px 14px; border-radius:8px; border:1.5px solid var(--dk-border);
  background:var(--dk-surface2); color:var(--dk-text-muted); font-size:12px; font-weight:500;
  cursor:pointer; transition:all .15s; font-family:inherit;
}
.atlas-filter-btn:hover, .atlas-type-btn:hover { border-color:#5b7cfa; color:#5b7cfa; }
.atlas-filter-btn.active, .atlas-type-btn.active { background:rgba(91,124,250,.15); border-color:#5b7cfa; color:#5b7cfa; }
</style>
@endpush

@push('script')
<script>
$(function() {
  let statusFilter = '', typeFilter = '';
  const table = $('#datatables').DataTable({
    processing: true,
    serverSide: true,
    ordering: true,
    order: [[1,'desc']],
    language: {
      search: 'Поиск:', lengthMenu: 'Показать _MENU_ записей',
      info: 'Записи _START_–_END_ из _TOTAL_', infoEmpty: 'Нет записей',
      zeroRecords: 'Ничего не найдено', paginate: { first:'«', last:'»', next:'›', previous:'‹' },
      processing: 'Загрузка...', emptyTable: 'Заказов пока нет'
    },
    ajax: {
      url: "{{ route('backend.admin.orders.index') }}",
      data: d => { d.status = statusFilter; d.order_type = typeFilter; }
    },
    columns: [
      {data:'DT_RowIndex',name:'DT_RowIndex'},
      {data:'saleId',name:'saleId'},
      {data:'customer',name:'customer'},
      {data:'item',name:'item'},
      {data:'order_type',name:'order_type'},
      {data:'sub_total',name:'sub_total'},
      {data:'discount',name:'discount'},
      {data:'total',name:'total'},
      {data:'paid',name:'paid'},
      {data:'due',name:'due'},
      {data:'status',name:'status'},
      {data:'action',name:'action'},
    ],
    drawCallback: function(s) {
      const count = s.fnRecordsTotal();
      $('#orders-count').text(count + ' заказов');
    }
  });

  $('.atlas-filter-btn').on('click', function() {
    $('.atlas-filter-btn').removeClass('active');
    $(this).addClass('active');
    statusFilter = $(this).data('status');
    table.ajax.reload();
  });

  $('.atlas-type-btn').on('click', function() {
    $('.atlas-type-btn').removeClass('active');
    $(this).addClass('active');
    typeFilter = $(this).data('type');
    table.ajax.reload();
  });

  // Отмена заказа
  $(document).on('click', '.btn-cancel', function() {
    const id  = $(this).data('id');
    const url = $(this).data('url');
    Swal.fire({
      title: 'Отменить заказ #' + id + '?',
      html: '<p style="color:#aaa;font-size:14px">Товары и ингредиенты вернутся на склад.<br>Действие необратимо.</p>',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e53e3e',
      cancelButtonColor: '#4a5568',
      confirmButtonText: '<i class="fas fa-ban"></i> Отменить заказ',
      cancelButtonText: 'Назад',
      background: '#13161f',
      color: '#e8eaf8',
    }).then(result => {
      if (!result.isConfirmed) return;
      $.ajax({
        url: url, type: 'POST',
        data: { _token: $('meta[name=csrf-token]').attr('content'), _method: 'DELETE' },
        success: res => {
          Swal.fire({ title: 'Готово!', text: res.message, icon: 'success',
            background: '#13161f', color: '#e8eaf8', timer: 2500, showConfirmButton: false });
          table.ajax.reload();
        },
        error: err => {
          Swal.fire({ title: 'Ошибка', text: err.responseJSON?.message || 'Попробуйте снова', icon: 'error',
            background: '#13161f', color: '#e8eaf8' });
        }
      });
    });
  });

});
</script>
@endpush
