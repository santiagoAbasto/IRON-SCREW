@extends('layouts.app')
@section('title','Detalle '.$order->number)
@section('content')
<div class="back-title order-detail-title"><a href="{{ route('orders.index') }}">‹</a><h1>{{ $order->number }}</h1>
 <div class="actions order-detail-actions">
 @if($order->items->isNotEmpty())
 <label class="print-size-control">Tamaño<select data-print-all-size><option value="80x50" selected>80 × 50 mm</option><option value="100x80">100 × 80 mm</option></select></label>
 <button class="secondary-button print-all-button" type="button" data-print-all-labels>Imprimir todas las etiquetas</button>
 @endif
 @if(strtolower($order->status??'')==='pendiente' && in_array('orders.manage',$ironUser->role?->permissions??[]))
 <form method="post" action="{{ route('orders.finalize',$order) }}" onsubmit="return confirm('¿Finalizar esta orden? Esta acción no podrá deshacerse desde el sistema.')">@csrf @method('patch')<button class="primary">✓ Finalizar orden</button></form>
 @endif
 </div>
</div>
<section class="panel order-summary"><h2>Detalle de OV - {{ $order->number }}</h2><div class="summary-grid"><label>Comprador<input value="{{ $order->customer }}" disabled></label><label>Fecha de creación<input value="{{ $order->created_on?->format('d/m/Y') }}" disabled></label><label>Estado<input value="{{ $order->status }}" disabled></label><label>Depósito<input value="{{ $order->warehouse ?: '—' }}" disabled></label></div></section>
@if($detailRefreshing)
<div class="sync-notice">
 <strong>Actualizando el detalle en segundo plano.</strong>
 @if($order->items->isNotEmpty())Se muestran los últimos artículos guardados; podés seguir trabajando normalmente.
 @else Los artículos aparecerán al recargar cuando Contabilium vuelva a responder.
 @endif
</div>
@elseif($order->detail_sync_status==='error')
<div class="sync-notice sync-notice-error">
 <div><strong>Contabilium no está disponible en este momento.</strong> Se muestran los últimos artículos guardados. La conexión general volverá a probarse automáticamente; para actualizar este detalle, usá Reintentar dentro de unos minutos.</div>
 <form method="post" action="{{ route('orders.refresh-detail',$order) }}">@csrf<button class="secondary-button">↻ Reintentar</button></form>
</div>
@endif
<h2 class="section-title">Artículos</h2>
<div class="data-table detail-table"><div class="thead"><span>CÓDIGO</span><span>DESCRIPCIÓN</span><span>CANTIDAD</span><span>UNIDADES X CAJA FRACCIONADO</span><span>UNIDADES X CAJA GRANEL</span><span>TOTAL CAJAS</span><span></span></div>
@forelse($order->items as $item)
@php
    $product = $products->get($item->code);
    $fractioned = (int) ($product?->units_fractioned ?? 0);
    $bulk = (int) ($product?->units_bulk ?? 0);
    $quantity = (float) $item->quantity;
    $bulkMatches = $bulk > 0 && abs(fmod($quantity, $bulk)) < 0.00001;
    $fractionedMatches = $fractioned > 0 && abs(fmod($quantity, $fractioned)) < 0.00001;
    $quantityMismatch = !$bulkMatches && !$fractionedMatches;
    $quantityReview = !$bulkMatches && $fractionedMatches && $fractioned > 0 && $quantity > $fractioned && $bulk > 0 && $quantity <= $bulk;
    $boxUnits = $bulkMatches ? $bulk : ($fractionedMatches ? $fractioned : ($bulk ?: $fractioned));
    $boxes = $boxUnits > 0 ? (int) ceil($quantity / $boxUnits) : 0;
    $boxType = $bulkMatches
        ? 'granel'
        : ($fractionedMatches
            ? ($boxes === 1 ? 'fraccionada' : 'fraccionadas')
            : ($bulk > 0 ? 'granel parcial' : ($fractioned > 0 ? 'fraccionada parcial' : '')));
@endphp
<div class="tr" data-item-row="{{ $item->id }}"><span>{{ $item->code ?: 'S/C' }}</span><span>{{ $item->description }}</span><span data-item-quantity="{{ $item->id }}" class="{{ $quantityMismatch ? 'quantity-mismatch' : ($quantityReview ? 'quantity-review' : '') }}" @if($quantityMismatch) title="La cantidad pedida no coincide con una caja completa de granel ni de fraccionado" @elseif($quantityReview) title="Cierra con fraccionado, pero también cabe en una caja granel. Revisá la presentación." @endif>{{ number_format($quantity,0,',','.') }}</span>
 <span>@if($fractioned)<button class="packaging-link" type="button" onclick="document.querySelector('#packaging-dialog-{{ $item->id }}').showModal()">{{ number_format($fractioned,0,',','.') }}</button>@elseif($product)<a class="packaging-link missing" href="{{ route('settings.products',['q'=>$product->code]) }}" title="Configurar este producto">0</a>@else 0 @endif</span>
 <span>@if($bulk)<button class="packaging-link" type="button" onclick="document.querySelector('#packaging-dialog-{{ $item->id }}').showModal()">{{ number_format($bulk,0,',','.') }}</button>@elseif($product)<a class="packaging-link missing" href="{{ route('settings.products',['q'=>$product->code]) }}" title="Configurar este producto">0</a>@else 0 @endif</span>
 <span class="box-total" data-item-box-total="{{ $item->id }}">
 @if(!$boxes) —
  @else
   <strong>{{ $boxes }}</strong><small>{{ $boxes===1?'caja':'cajas' }} {{ $boxType }}</small>
   @if($quantityReview)<em class="packaging-review">Revisar presentación</em>@endif
  @endif
 </span><button class="printer" type="button" aria-label="Imprimir etiqueta" data-label-open="label-dialog-{{ $item->id }}"><img src="{{ asset('assets/figma/printer.svg') }}" alt=""></button></div>
@if($product)
<dialog class="form-dialog packaging-dialog" id="packaging-dialog-{{ $item->id }}">
 <form method="post" action="{{ route('settings.products.packaging',$product) }}">@csrf @method('put')
  <button type="button" class="dialog-close" onclick="this.closest('dialog').close()">×</button>
  <h2>Configurar presentación</h2>
  <p class="label-product"><strong>{{ $product->code }}</strong> · {{ $product->description }}</p>
  <div class="form-grid">
   <label>Unidades por caja fraccionado (opcional)<input type="number" min="1" name="units_fractioned" value="{{ $fractioned ?: '' }}" autofocus></label>
   <label>Unidades por caja granel<input type="number" min="1" name="units_bulk" value="{{ $bulk ?: '' }}" required></label>
  </div>
  <p class="configuration-help">Granel es obligatorio. Fraccionado puede quedar vacío y completarse más adelante, incluso manualmente al preparar una impresión.</p>
  <button class="primary">Guardar presentación</button>
 </form>
</dialog>
@endif
<dialog class="form-dialog label-dialog" id="label-dialog-{{ $item->id }}" data-label-dialog data-item-id="{{ $item->id }}" data-code="{{ $item->code }}" data-description="{{ $item->description }}" data-quantity="{{ $quantity }}" data-fractioned="{{ $fractioned }}" data-bulk="{{ $bulk }}" data-customer="{{ $order->customer }}" data-order="{{ $order->number }}" data-logo="{{ asset('assets/figma/label-logo-bw.jpg') }}">
 <button type="button" class="dialog-close" data-dialog-close>×</button><h2>Imprimir etiquetas</h2><p class="label-product"><strong>{{ $item->code }}</strong> · {{ $item->description }}</p>
 @if(!$fractioned||!$bulk)<div class="quantity-alert"><strong>Presentación pendiente de configurar.</strong><br>Podés indicar las unidades manualmente o completar el producto desde Configuración.</div>@endif
 <div class="label-summary"><span>Pedido <b>{{ number_format($quantity,0,',','.') }}</b></span><span>Fraccionado <b>{{ $fractioned?:'—' }}</b></span><span>Granel <b>{{ $bulk?:'—' }}</b></span></div>
 <div class="form-grid"><label>Tipo de etiqueta<select data-label-type><option value="bulk">Granel</option><option value="fractioned">Fraccionado{{ $fractioned?' ('.number_format($fractioned,0,',','.').')':'' }}</option></select></label><label>Tamaño de etiqueta<select data-label-size><option value="80x50" selected>80 × 50 mm</option><option value="100x80">100 × 80 mm</option></select></label><label>Cantidad a imprimir por etiqueta<input type="number" min="1" placeholder="Ingresar unidades" data-units-per-label></label><label>Total de cajas / etiquetas<input type="number" min="1" data-label-count></label></div>
 <div class="quantity-alert" data-quantity-alert hidden></div><p class="label-help" data-label-help></p>
 <div class="label-preview" data-label-preview><div class="thermal-label"><div class="thermal-customer">{{ strtoupper($order->customer) }}</div><div class="thermal-product"><strong>{{ $item->description }}</strong><span>{{ $item->code }}</span><b data-preview-units>— UNIDADES</b></div><div class="thermal-brand"><img src="{{ asset('assets/figma/label-logo-bw.jpg') }}" alt="Iron Screw"><em data-preview-type>GRANEL</em></div></div></div>
 <div class="label-dialog-actions"><button class="secondary-button" type="button" data-save-label-adjustment>Guardar ajuste</button><button class="primary" type="button" data-print-labels>Imprimir etiqueta</button></div>
</dialog>
@empty
<div class="empty">Todavía no hay artículos guardados para esta orden. La actualización quedó en cola.</div>
@endforelse</div>
<section id="label-print-area" aria-hidden="true"></section>
@endsection
