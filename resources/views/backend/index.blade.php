@extends('backend.master')
@section('title', 'Дашборд')
@section('content')
<div class="dash-wrap">

  {{-- KPI CARDS --}}
  <div class="kpi-grid">
    <div class="kpi-card kpi-blue">
      <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
      <div class="kpi-body">
        <div class="kpi-label">Выручка сегодня</div>
        <div class="kpi-value">{{ currency()->symbol??'₽' }} {{ number_format($todaySale ?? 0, 2) }}</div>
        <div class="kpi-trend up"><i class="fas fa-arrow-up"></i> За сегодня</div>
      </div>
    </div>
    <div class="kpi-card kpi-green">
      <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
      <div class="kpi-body">
        <div class="kpi-label">Заказов сегодня</div>
        <div class="kpi-value">{{ $todayOrder ?? 0 }}</div>
        <div class="kpi-trend up"><i class="fas fa-arrow-up"></i> За сегодня</div>
      </div>
    </div>
    <div class="kpi-card kpi-purple">
      <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
      <div class="kpi-body">
        <div class="kpi-label">Выручка за месяц</div>
        <div class="kpi-value">{{ currency()->symbol??'₽' }} {{ number_format($thisMonthSale ?? 0, 2) }}</div>
        <div class="kpi-trend neutral"><i class="fas fa-calendar"></i> Этот месяц</div>
      </div>
    </div>
    <div class="kpi-card kpi-orange">
      <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
      <div class="kpi-body">
        <div class="kpi-label">Заказов за месяц</div>
        <div class="kpi-value">{{ $thisMonthOrder ?? 0 }}</div>
        <div class="kpi-trend neutral"><i class="fas fa-calendar"></i> Этот месяц</div>
      </div>
    </div>
  </div>

  {{-- CHARTS ROW --}}
  <div class="dash-charts-row">
    <div class="dash-chart-card">
      <div class="dash-card-head">
        <span><i class="fas fa-chart-area"></i> Продажи за 7 дней</span>
      </div>
      <div class="dash-chart-body">
        <canvas id="weeklyChart" height="80"></canvas>
      </div>
    </div>
    <div class="dash-chart-card">
      <div class="dash-card-head">
        <span><i class="fas fa-chart-bar"></i> Продажи по месяцам</span>
      </div>
      <div class="dash-chart-body">
        <canvas id="monthlyChart" height="80"></canvas>
      </div>
    </div>
  </div>

  {{-- RECENT ORDERS --}}
  <div class="dash-card">
    <div class="dash-card-head">
      <span><i class="fas fa-history"></i> Последние заказы</span>
      @can('sale_view')
      <a href="{{ route('backend.admin.orders.index') }}" class="dash-link">Все заказы →</a>
      @endcan
    </div>
    <div class="dash-card-body table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Клиент</th>
            <th>Сумма</th>
            <th>Тип</th>
            <th>Статус</th>
            <th>Дата</th>
          </tr>
        </thead>
        <tbody>
          @forelse($latestOrders ?? [] as $order)
          <tr>
            <td><span class="order-id">#{{ $order->id }}</span></td>
            <td>{{ $order->customer->name ?? 'Гость' }}</td>
            <td><strong>{{ currency()->symbol??'₽' }} {{ number_format($order->total, 2) }}</strong></td>
            <td>
              <span class="type-badge {{ $order->order_type === 'dine_in' ? 'type-dine' : 'type-take' }}">
                {{ $order->order_type === 'dine_in' ? '🍽 В зале' : '🥡 На вынос' }}
              </span>
            </td>
            <td>
              <span class="status-badge {{ $order->payment_status === 'paid' ? 'status-paid' : 'status-due' }}">
                {{ $order->payment_status === 'paid' ? 'Оплачен' : 'Долг' }}
              </span>
            </td>
            <td style="color:var(--dk-text-muted);font-size:12px">{{ $order->created_at->diffForHumans() }}</td>
          </tr>
          @empty
          <tr><td colspan="6" style="text-align:center;color:var(--dk-text-muted);padding:32px">Заказов пока нет</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

@push('style')
<style>
.dash-wrap { display:flex; flex-direction:column; gap:20px; }
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
@media(max-width:900px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:540px){.kpi-grid{grid-template-columns:1fr}}
.kpi-card { background:var(--dk-surface); border:1px solid var(--dk-border); border-radius:14px; padding:20px; display:flex; gap:16px; align-items:flex-start; transition:transform .2s,box-shadow .2s; }
.kpi-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.3); }
.kpi-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.kpi-blue .kpi-icon  { background:rgba(91,124,250,.15); color:#5b7cfa; }
.kpi-green .kpi-icon { background:rgba(32,201,151,.15); color:#20c997; }
.kpi-purple .kpi-icon{ background:rgba(168,85,247,.15);  color:#a855f7; }
.kpi-orange .kpi-icon{ background:rgba(251,191,36,.15);  color:#fbbf24; }
.kpi-label { font-size:12px; font-weight:500; color:var(--dk-text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:.8px; }
.kpi-value { font-size:26px; font-weight:800; color:var(--dk-text); margin-bottom:6px; }
.kpi-trend { font-size:11px; font-weight:500; }
.kpi-trend.up     { color:#20c997; }
.kpi-trend.down   { color:#ff5470; }
.kpi-trend.neutral{ color:var(--dk-text-muted); }
.dash-charts-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:700px){.dash-charts-row{grid-template-columns:1fr}}
.dash-chart-card,.dash-card { background:var(--dk-surface); border:1px solid var(--dk-border); border-radius:14px; overflow:hidden; }
.dash-card-head { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:1px solid var(--dk-border); font-size:13px; font-weight:600; color:var(--dk-text); }
.dash-card-head i { color:#5b7cfa; margin-right:7px; }
.dash-link { font-size:12px; color:#5b7cfa; text-decoration:none; }
.dash-link:hover { text-decoration:underline; }
.dash-chart-body { padding:16px; }
.dash-card-body { padding:0; }
.order-id { font-weight:600; color:#5b7cfa; }
.type-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.type-dine { background:rgba(168,85,247,.15); color:#a855f7; }
.type-take { background:rgba(91,124,250,.15); color:#5b7cfa; }
.status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.status-paid { background:rgba(32,201,151,.15); color:#20c997; }
.status-due  { background:rgba(255,84,112,.15); color:#ff5470; }
</style>
@endpush

@push('script')
<script>
const chartDefaults = {
  responsive: true, maintainAspectRatio: true,
  plugins: { legend: { display: false }, tooltip: { backgroundColor:'#1a1d2e', titleColor:'#e8eaf8', bodyColor:'#8b92b8', borderColor:'#252b45', borderWidth:1 } },
  scales: {
    x: { grid: { color:'rgba(255,255,255,.05)' }, ticks: { color:'#8b92b8', font:{size:11} } },
    y: { grid: { color:'rgba(255,255,255,.05)' }, ticks: { color:'#8b92b8', font:{size:11} } }
  }
};
const weeklyCtx = document.getElementById('weeklyChart');
if(weeklyCtx) {
  new Chart(weeklyCtx, {
    type: 'line',
    data: {
      labels: {!! json_encode($weeklySalesLabels ?? ['Пн','Вт','Ср','Чт','Пт','Сб','Вс']) !!},
      datasets: [{
        data: {!! json_encode($weeklySalesData ?? [0,0,0,0,0,0,0]) !!},
        borderColor:'#5b7cfa', backgroundColor:'rgba(91,124,250,.1)',
        fill:true, tension:.4, pointBackgroundColor:'#5b7cfa', pointRadius:4
      }]
    },
    options: chartDefaults
  });
}
const monthlyCtx = document.getElementById('monthlyChart');
if(monthlyCtx) {
  new Chart(monthlyCtx, {
    type: 'bar',
    data: {
      labels: {!! json_encode($monthlySalesLabels ?? ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек']) !!},
      datasets: [{
        data: {!! json_encode($monthlySalesData ?? array_fill(0,12,0)) !!},
        backgroundColor:'rgba(32,201,151,.5)', borderColor:'#20c997', borderWidth:2, borderRadius:6
      }]
    },
    options: chartDefaults
  });
}
</script>
@endpush
@endsection
