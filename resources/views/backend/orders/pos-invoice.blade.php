@extends('backend.master')
@section('title', 'Receipt_'.$order->id)
@section('content')

<div class="card">
  <!-- Thermal receipt -->
  <div class="receipt-container mt-0" id="printable-section" style="max-width: {{ $maxWidth}}; font-size: 12px; font-family: 'Courier New', Courier, monospace;">
    <div class="text-center">
      @if(readConfig('is_show_logo_invoice'))
      <img src="{{ assetImage(readconfig('site_logo')) }}" height="30" width="70" alt="Logo">
      @endif
      @if(readConfig('is_show_site_invoice'))
      <h3>{{ readConfig('site_name') }}</h3>
      @endif
      @if(readConfig('is_show_address_invoice')){{ readConfig('contact_address') }}<br>@endif
      @if(readConfig('is_show_phone_invoice')){{ readConfig('contact_phone') }}<br>@endif
      @if(readConfig('is_show_email_invoice')){{ readConfig('contact_email') }}<br>@endif
    </div>

    {{ 'Kassir: '.auth()->user()->name}}<br>
    {{ 'Sargyt: #'.$order->id}}<br>

    {{-- Order Type badge --}}
    @if(($order->order_type ?? 'takeaway') === 'dine_in')
      <span style="display:inline-block;background:#e8724a;color:#fff;padding:1px 6px;border-radius:3px;font-weight:bold;">🍽 ZAL</span>
    @else
      <span style="display:inline-block;background:#555;color:#fff;padding:1px 6px;border-radius:3px;font-weight:bold;">🥡 ALYP GITMEK</span>
    @endif
    <br>

    <hr>
    <div class="row justify-content-between mx-auto">
      <div class="text-left">
        @if(readConfig('is_show_customer_invoice'))
        <address>
          Ady: {{ $order->customer->name ?? 'N/A' }}<br>
          Adresi: {{ $order->customer->address ?? 'N/A' }}<br>
          Telefon: {{ $order->customer->phone ?? 'N/A' }}
        </address>
        @endif
      </div>
      <div class="text-right">
        <address class="text-right">
          <p>{{ date('d-M-Y') }}</p>
          <p>{{ date('h:i:s A') }}</p>
        </address>
      </div>
    </div>
    <hr>
    <table style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: left;">Haryt</th>
          <th style="text-align: right;"></th>
          <th style="text-align: right;">Jemi {{ currency()->symbol}}</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($order->products as $item)
        <tr>
          <td>{{ $item->product->name }}</td>
          <td class="text-right">{{ $item->quantity }}*{{ $item->discounted_price}}</td>
          <td class="text-right">{{ $item->total }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <hr>
    <div class="summary">
      <table style="width: 100%;">
        <tr>
          <td>Jemi:</td>
          <td class="text-right">{{number_format($order->sub_total, 2) }}</td>
        </tr>
        <tr>
          <td>Arzanladys:</td>
          <td class="text-right">{{number_format($order->discount, 2) }}</td>
        </tr>
        <tr>
          <td><strong>Tolemeli:</strong></td>
          <td class="text-right"><strong>{{number_format($order->total, 2) }}</strong></td>
        </tr>
        <tr>
          <td>Tolenen:</td>
          <td class="text-right">{{number_format($order->paid, 2) }}</td>
        </tr>
        <tr>
          <td>Galyk:</td>
          <td class="text-right">{{number_format($order->due, 2) }}</td>
        </tr>
      </table>
    </div>
    <hr>
    <div class="text-center">
      <p class="text-muted" style="font-size: 12px;">@if(readConfig('is_show_note_invoice')){{ readConfig('note_to_customer_invoice') }}@endif</p>
    </div>
  </div>

  <!-- Print Button -->
  <div class="text-center mt-3 no-print pb-3">
    <button type="button" onclick="window.print()" class="btn bg-gradient-primary text-white"><i class="fas fa-print"></i> Cap et</button>
  </div>
</div>
@endsection

@push('style')
<style>
  .receipt-container {
    border: 1px dotted #000;
    padding: 8px;
  }
  hr {
    border: none;
    border-top: 1px dashed #000;
    margin: 5px 0;
  }
  table { width: 100%; }
  td, th { padding: 2px 0; }
  .text-right { text-align: right; }
  @media print {
    @page {
      margin-top: 5px !important;
      margin-left: 0px !important;
      padding-left: 0px !important;
    }
    footer { display: none !important; }
  }
</style>
@endpush

@push('script')
<script>
  window.print();
</script>
@endpush
