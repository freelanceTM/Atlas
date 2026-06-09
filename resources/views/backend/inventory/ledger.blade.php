@extends('backend.master')
@section('title', 'Журнал склада')

@section('content')
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title"><i class="fas fa-book mr-2"></i> Журнал движения склада</h3>
    <a href="{{ route('backend.admin.ingredients.index') }}" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> К ингредиентам
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="ledger-table">
        <thead style="background:#f8f9fa;">
          <tr>
            <th>#</th>
            <th>Дата</th>
            <th>Ингредиент</th>
            <th>Тип</th>
            <th>Количество</th>
            <th>Ссылка</th>
            <th>Пользователь</th>
            <th>Примечание</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transactions as $txn)
          <tr>
            <td>{{ $txn->id }}</td>
            <td><small>{{ $txn->created_at->format('d.m.Y H:i') }}</small></td>
            <td><strong>{{ $txn->ingredient->name ?? '—' }}</strong>
                <small class="text-muted">{{ $txn->ingredient->unit ?? '' }}</small></td>
            <td>
              @if($txn->type === 'consume')
                <span class="badge bg-danger">− Списание</span>
              @elseif($txn->type === 'restore')
                <span class="badge bg-success">+ Возврат</span>
              @elseif($txn->type === 'purchase')
                <span class="badge bg-primary">+ Приход</span>
              @else
                <span class="badge bg-secondary">Корректировка</span>
              @endif
            </td>
            <td>
              @if(in_array($txn->type, ['consume']))
                <span class="text-danger">{{ number_format($txn->quantity, 3) }}</span>
              @else
                <span class="text-success">+{{ number_format($txn->quantity, 3) }}</span>
              @endif
              <small class="text-muted">{{ $txn->ingredient->unit ?? '' }}</small>
            </td>
            <td>
              @if($txn->reference_type === 'order')
                <a href="{{ route('backend.admin.orders.pos-invoice', $txn->reference_id) }}" class="badge bg-info text-white">
                  Заказ #{{ $txn->reference_id }}
                </a>
              @elseif($txn->reference_type === 'purchase')
                <span class="badge bg-secondary">Закупка #{{ $txn->reference_id }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td><small>{{ $txn->user->name ?? '—' }}</small></td>
            <td><small class="text-muted">{{ $txn->note }}</small></td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
              Нет записей в журнале
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($transactions->hasPages())
  <div class="card-footer">
    {{ $transactions->links() }}
  </div>
  @endif
</div>
@endsection

@push('script')
<script>
$(function() {
  // Filter by type
  $('[data-filter]').on('click', function() {
    $('[data-filter]').removeClass('active');
    $(this).addClass('active');
    const type = $(this).data('filter');
    if (!type) { $('#ledger-table tbody tr').show(); return; }
    $('#ledger-table tbody tr').each(function() {
      $(this).toggle($(this).find('[class*="badge"]').text().toLowerCase().includes(type));
    });
  });
});
</script>
@endpush
