<div class="sidebar-backdrop" data-sidebar-backdrop></div>
<aside class="sidebar" id="app-sidebar" aria-hidden="true">
    <div class="sidebar-brand">
        <div><strong>Porpora</strong><span>Sistema de Producción</span></div>
        <button type="button" aria-label="Cerrar menú" data-sidebar-close><img src="{{ asset('assets/figma/sidebar-close.svg') }}" alt=""></button>
    </div>

    <nav class="sidebar-nav" aria-label="Navegación principal">
        @if(in_array('orders.view',$ironUser->role?->permissions??[]))<a class="{{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">
            <img src="{{ asset('assets/figma/nav-orders.svg') }}" alt=""><span>Órdenes de venta</span>
        </a>@endif
        @if(in_array('settings.view',$ironUser->role?->permissions??[]))
        <a class="{{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
            <img src="{{ asset('assets/figma/nav-settings.svg') }}" alt=""><span>Configuración</span>
        </a>
        @endif
    </nav>

    <form class="sidebar-logout-form" method="post" action="{{ route('logout') }}">
        @csrf
        <button class="sidebar-logout" type="submit">
            <img src="{{ asset('assets/figma/logout.svg') }}" alt=""><span>Cerrar Sesión</span>
        </button>
    </form>
</aside>
