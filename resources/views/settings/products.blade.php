@extends('layouts.app')
@section('content')
<div class="back-title"><a href="{{ route('settings.index') }}">‹</a><h1>Gestión de productos</h1></div>
@if(session('import_report'))
@php($report=session('import_report'))
<section class="import-report">
 <div><strong>{{ $report['products_updated'] }}</strong><span>productos actualizados</span></div>
 <div><strong>{{ count($report['new']) }}</strong><span>cantidades nuevas</span></div>
 <div><strong>{{ count($report['changed']) }}</strong><span>cantidades modificadas</span></div>
 <div><strong>{{ $report['unchanged'] }}</strong><span>productos sin cambios</span></div>
 @if($report['unknown'] ?? [])<details><summary>{{ count($report['unknown']) }} códigos de la planilla no existen en Contabilium y no se importaron</summary><ul>@foreach($report['unknown'] as $code)<li><b>{{ $code }}</b></li>@endforeach</ul></details>@endif
 @if($report['new'])
 <details open><summary>Cantidades nuevas</summary><ul>@foreach($report['new'] as $item)<li><b>{{ $item['code'] }}</b> · {{ $item['type'] }}: {{ number_format($item['to'],0,',','.') }}</li>@endforeach</ul></details>
 @endif
 @if($report['changed'])
 <details open><summary>Cantidades modificadas</summary><ul>@foreach($report['changed'] as $item)<li><b>{{ $item['code'] }}</b> · {{ $item['type'] }}: {{ number_format($item['from'],0,',','.') }} → {{ number_format($item['to'],0,',','.') }}</li>@endforeach</ul></details>
 @endif
</section>
@endif
<section class="panel">
 <div class="panel-head"><h2>Catálogo de Productos</h2></div>
 <div class="product-tools">
  <form class="search" method="get"><img src="{{ asset('assets/figma/search.svg') }}" alt=""><input name="q" value="{{ $q }}" placeholder="Buscar por código o descripción..."></form>
  @if(in_array('products.manage',$ironUser->role?->permissions??[]))
  <a class="secondary-button" href="{{ route('settings.products.bulk-template') }}">↓ Descargar plantilla</a>
  <button class="primary" type="button" onclick="document.querySelector('#import-products').showModal()">↑ Subir Excel</button>
  @endif
 </div>
 <div class="data-table product-table">
  <div class="thead"><span>CÓDIGO</span><span>DESCRIPCIÓN</span><span>UNIDADES X CAJA<br>FRACCIONADOS</span><span>UNIDADES X CAJA<br>GRANEL</span><span></span></div>
  @forelse($products as $product)
  <div class="tr"><span>{{ $product->code }}</span><span>{{ $product->description }}</span><span>{{ $product->units_fractioned ?: '—' }}</span><span>{{ $product->units_bulk }}</span>
   <span class="action-buttons">
    <button class="printer" type="button" title="Imprimir etiquetas" aria-label="Imprimir etiquetas" data-label-open="product-label-dialog-{{ $product->id }}"><img src="{{ asset('assets/figma/printer.svg') }}" alt=""></button>
    @if(in_array('products.manage',$ironUser->role?->permissions??[]))
    <button type="button" title="Editar" onclick="document.querySelector('#edit-product-{{ $product->id }}').showModal()"><img src="{{ asset('assets/figma/action-edit.svg') }}" alt="Editar"></button>
    <form method="post" action="{{ route('settings.products.destroy',$product) }}" onsubmit="return confirm('¿Eliminar este producto del catálogo local?')">@csrf @method('delete')<button title="Eliminar"><img src="{{ asset('assets/figma/action-delete.svg') }}" alt="Eliminar"></button></form>
    @endif
   </span>
  </div>
  <dialog class="form-dialog label-dialog" id="product-label-dialog-{{ $product->id }}" data-label-dialog data-standalone="true" data-code="{{ $product->code }}" data-description="{{ $product->description }}" data-quantity="0" data-fractioned="{{ (int)$product->units_fractioned }}" data-bulk="{{ (int)$product->units_bulk }}" data-customer="" data-order="" data-logo="{{ asset('assets/figma/label-logo.png') }}">
   <button type="button" class="dialog-close" data-dialog-close>×</button>
   <h2>Imprimir etiquetas de producto</h2>
   <p class="label-product"><strong>{{ $product->code }}</strong> · {{ $product->description }}</p>
   @if(!$product->units_fractioned||!$product->units_bulk)<div class="quantity-alert"><strong>Presentación parcialmente configurada.</strong><br>Podés ingresar manualmente las unidades para esta impresión.</div>@endif
   <div class="label-summary two"><span>Fraccionado <b>{{ $product->units_fractioned?:'—' }}</b></span><span>Granel <b>{{ $product->units_bulk?:'—' }}</b></span></div>
   <div class="form-grid"><label>Tipo de etiqueta<select data-label-type><option value="bulk">Granel</option><option value="fractioned">Fraccionado{{ $product->units_fractioned?' ('.number_format($product->units_fractioned,0,',','.').')':'' }}</option></select></label><label>Unidades por etiqueta<input type="number" min="1" placeholder="Ingresar unidades" data-units-per-label></label><label>Total de cajas / etiquetas<input type="number" min="1" value="1" data-label-count></label></div>
   <div class="quantity-alert" data-quantity-alert hidden></div><p class="label-help" data-label-help></p>
   <div class="label-preview" data-label-preview><div class="thermal-label no-customer"><div class="thermal-product"><strong>{{ $product->description }}</strong><span>{{ $product->code }}</span><b data-preview-units>— UNIDADES</b></div><div class="thermal-brand"><img src="{{ asset('assets/figma/label-logo.png') }}" alt=""><div><strong>IRON<br>SCREW</strong><small>TORNILLOS AUTOPERFORANTES</small></div><em data-preview-type>GRANEL</em></div></div></div>
   <button class="primary" type="button" data-print-labels>Imprimir etiquetas</button>
  </dialog>
  @if(in_array('products.manage',$ironUser->role?->permissions??[]))
  <dialog class="form-dialog" id="edit-product-{{ $product->id }}"><form method="post" action="{{ route('settings.products.update',$product) }}">@csrf @method('put')<button type="button" class="dialog-close" onclick="this.closest('dialog').close()">×</button><h2>Editar producto</h2>@include('settings.partials.product-form',['product'=>$product])<button class="primary">Guardar cambios</button></form></dialog>
  @endif
  @empty <div class="empty">No hay productos para mostrar.</div>@endforelse
 </div>
</section>
<dialog class="form-dialog import-dialog" id="import-products">
 <form method="post" action="{{ route('settings.products.bulk-import') }}" enctype="multipart/form-data">@csrf
  <button type="button" class="dialog-close" onclick="this.closest('dialog').close()">×</button>
  <h2>Actualizar cantidades de fraccionado y granel</h2>
  <p class="configuration-help">Subí la plantilla para actualizar Fraccionado y Granel. En el archivo original, Fracción x 100 tiene prioridad y reemplaza a Fracción 1 cuando ambas tienen valor; Fracción 2 se ignora. No modifiques códigos ni nombres. En la plantilla descargada, una celda vacía conserva el valor actual.</p>
  <label class="file-field">Seleccionar plantilla Excel (.xlsx)<input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
  <button class="primary">Actualizar fraccionado y granel</button>
 </form>
</dialog>
<section id="label-print-area" aria-hidden="true"></section>
@endsection
