@extends('layouts.app')
@section('title','Órdenes de venta')
@section('content')
<div class="title-row">
 <div><h1>Órdenes de venta</h1>@if($lastSync)<small class="sync-time">Sincronizado {{ $lastSync->diffForHumans() }}</small>@endif</div>
 <div class="actions">
  <form class="search" method="get"><img src="{{ asset('assets/figma/search.svg') }}" alt=""><input name="q" value="{{ $q }}" placeholder="Buscar por orden o comprador..."></form>
  @if(in_array('orders.manage',$ironUser->role?->permissions??[]))<form method="post" action="{{ route('orders.sync') }}" data-sync-form>@csrf<button class="primary" data-sync-button>↻ Sincronizar</button></form>@endif
 </div>
</div>
<div class="data-table orders-table">
 <div class="thead"><span>N° ORDEN</span><span>COMPRADOR</span><span>FECHA CREACIÓN</span><span>ESTADO</span><span></span></div>
 @forelse($orders as $order)
 @php($statusClass=match(strtolower($order->status??'')){'pendiente'=>'pending','cancelado'=>'cancelled',default=>'active'})
 <a class="tr" href="{{ route('orders.show',$order) }}"><span>{{ $order->number }}</span><span>{{ $order->customer }}</span><span>{{ $order->created_on?->format('d/m/Y') }}</span><span><b class="badge {{ $statusClass }}">{{ $order->status }}</b></span><img src="{{ asset('assets/figma/chevron.svg') }}" alt=""></a>
 @empty <div class="empty">No hay órdenes sincronizadas con ese criterio.</div>@endforelse
</div>
@if($orders->hasPages())
@php($start=max(1,$orders->currentPage()-2))
@php($end=min($orders->lastPage(),$orders->currentPage()+2))
<nav class="iron-pagination" aria-label="Paginación de órdenes">
 <span class="pagination-summary">{{ $orders->firstItem() }}–{{ $orders->lastItem() }} de {{ $orders->total() }}</span>
 <div class="pagination-pages">
  @if($orders->onFirstPage())<span class="page-control disabled" aria-disabled="true">‹</span>@else<a class="page-control" href="{{ $orders->previousPageUrl() }}" rel="prev" aria-label="Página anterior">‹</a>@endif
  @if($start>1)<a href="{{ $orders->url(1) }}">1</a>@if($start>2)<span class="ellipsis">…</span>@endif @endif
  @for($page=$start;$page<=$end;$page++)
   @if($page===$orders->currentPage())<span class="current" aria-current="page">{{ $page }}</span>@else<a href="{{ $orders->url($page) }}">{{ $page }}</a>@endif
  @endfor
  @if($end<$orders->lastPage())@if($end<$orders->lastPage()-1)<span class="ellipsis">…</span>@endif<a href="{{ $orders->url($orders->lastPage()) }}">{{ $orders->lastPage() }}</a>@endif
  @if($orders->hasMorePages())<a class="page-control" href="{{ $orders->nextPageUrl() }}" rel="next" aria-label="Página siguiente">›</a>@else<span class="page-control disabled" aria-disabled="true">›</span>@endif
 </div>
</nav>
@endif
@endsection
