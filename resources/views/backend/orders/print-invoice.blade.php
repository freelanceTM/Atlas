@extends('backend.master')
@section('title', 'Invoice_'.$order->id)
@section('content')
<div class="card">
  <div class="card-body">
    <section class="invoice">
      <!-- Title row -->
      <div class="row mb-4">
        <div class="col-4">
          <h2 class="page-header">
            @if(readConfig('is_show_logo_invoice'))
            <img src="{{ assetImage(readconfig('site_logo')) }}" height="40" width="40" alt="Logo"
              class="brand-image img-circle elevation-3" style="opacity: .8">
            @endif
            @if(readConfig('is_show_site_invoice')){{ readConfig('site_name') }} @endif
          </h2>
        </div>
        <div class="col-4">
          <h4 class="page-header">Hasap-faktura</h4>
        </div>
        <div class="col-4">
          <small class="float-right text-small">Senesi: {{date('d/m/Y')}}</small>
        </div>
      </div>

      <!-- Info row -->
      <div class="row invoice-info">
        <div class="col-sm-5 invoice-col">
          @if(readConfig('is_show_customer_invoice'))
          Alyjy
          <address>
            <strong>Ady: {{$order->customer->name??"N/A"}}</strong><br>
            Adresi: {{$order->customer->address??"N/A"}}<br>
            Telefon: {{$order->customer->phone??"N/A"}}<br>
          </address>
          @endif
        </div>
        <div class="col-sm-4 invoice-col">
          Satyjy
          <address>
            @if(readConfig('is_show_site_invoice'))<strong>Ady: {{ readConfig('site_name') }}</strong><br> @endif
            @if(readConfig('is_show_address_invoice'))Adresi: {{ readConfig('contact_address') }}<br>@endif
            @if(readConfig('is_show_phone_invoice'))Telefon: {{ readConfig('contact_phone') }}<br>@endif
            @if(readConfig('is_show_email_invoice'))Email: {{ readConfig('contact_email') }}<br>@endif
          </address>
        </div>
        <div class="col-sm-3 invoice-col">
          Maglumat <br>
          Sargyt No: #{{$order->id}}<br>
          Senesi: {{date('d/m/Y', strtotime($order->created_at))}}<br>
          Gornusi:
          @if(($order->order_type ?? 'takeaway') === 'dine_in')
            <span style="display:inline-block;background:#e8724a;color:#fff;padding:1px 8px;border-radius:4px;font-weight:600;">🍽 Zalda</span>
          @else
            <span style="display:inline-block;background:#555;color:#fff;padding:1px 8px;border-radius:4px;font-weight:600;">🥡 Alyp gitmek</span>
          @endif
        </div>
      </div>

      <!-- Table row -->
      <div class="row">
        <div class="col-12 table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>No</th>
                <th>Haryt ady</th>
                <th>Mukdary</th>
                <th>Bahasy {{currency()->symbol??''}}</th>
                <th>Jemi {{currency()->symbol??''}}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($order->products as $item)
              <tr>
                <td>{{$loop->index + 1}}</td>
                <td>{{$item->product->name}}</td>
                <td>{{$item->quantity}} {{optional($item->product->unit)->short_name}}</td>
                <td>
                  {{$item->discounted_price}}
                  @if ($item->price > $item->discounted_price)
                  <br><del>{{ $item->price }}</del>
                  @endif
                </td>
                <td>{{$item->total}}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <div class="row">
        <div class="col-6">
          <p class="text-muted well well-sm shadow-none" style="margin-top: 10px;">
            @if(readConfig('is_show_note_invoice')){{ readConfig('note_to_customer_invoice') }}@endif
          </p>
        </div>
        <div class="col-6">
          <div class="table-responsive">
            <table class="table">
              <tr>
                <th style="width:50%">Jemi:</th>
                <td class="text-right">{{currency()->symbol.' '.number_format($order->sub_total,2,'.',',')}}</td>
              </tr>
              <tr>
                <th>Arzanladys:</th>
                <td class="text-right">{{currency()->symbol.' '.number_format($order->discount,2,'.',',')}}</td>
              </tr>
              <tr>
                <th>Tolemeli:</th>
                <td class="text-right">{{currency()->symbol.' '.number_format($order->total,2,'.',',')}}</td>
              </tr>
              <tr>
                <th>Tolenen:</th>
                <td class="text-right">{{currency()->symbol.' '.number_format($order->paid,2,'.',',')}}</td>
              </tr>
              <tr>
                <th>Galyk:</th>
                <td class="text-right">{{currency()->symbol.' '.number_format($order->due,2,'.',',')}}</td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <div class="row no-print">
        <div class="col-12">
          <button type="button" onclick="window.print()" class="btn btn-success float-right">
            <i class="fas fa-print"></i> Cap et
          </button>
        </div>
      </div>
    </section>
  </div>
</div>
@endsection

@push('style')
<style>
  .invoice { border: none !important; }
</style>
@endpush

@push('script')
<script>
  window.addEventListener("load", window.print());
</script>
@endpush
