@extends('layouts.app')
@section('content')
<h1>Configuración del sistema</h1>
<div class="settings-cards">
@if(in_array('users.manage',$ironUser->role?->permissions??[]))<a href="{{ route('settings.users') }}"><i><img src="{{ asset('assets/figma/settings-circle.svg') }}" alt=""><img src="{{ asset('assets/figma/settings-users.svg') }}" alt=""></i><h2>Usuarios</h2><p>Administrar usuarios</p></a>@endif
@if(in_array('roles.manage',$ironUser->role?->permissions??[]))<a href="{{ route('settings.roles') }}"><i><img src="{{ asset('assets/figma/settings-circle.svg') }}" alt=""><img src="{{ asset('assets/figma/settings-roles.svg') }}" alt=""></i><h2>Roles</h2><p>Administrar roles</p></a>@endif
@if(in_array('products.view',$ironUser->role?->permissions??[]))<a href="{{ route('settings.products') }}"><i><img src="{{ asset('assets/figma/settings-circle.svg') }}" alt=""><img src="{{ asset('assets/figma/settings-products.svg') }}" alt=""></i><h2>Productos</h2><p>Administrar productos</p></a>@endif
</div>
@endsection
