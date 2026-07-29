@extends('layouts.app')
@section('content')
<div class="back-title"><a href="{{ route('settings.index') }}">‹</a><h1>Gestión de Usuarios</h1></div>
<section class="panel">
 <div class="panel-head"><h2>Usuarios del Sistema</h2><button class="primary" type="button" onclick="document.querySelector('#new-user').showModal()">＋ Nuevo Usuario</button></div>
 <div class="data-table users-table">
  <div class="thead"><span>USUARIO ABM</span><span>USUARIO</span><span>EMAIL</span><span>ROL</span><span>ESTADO</span><span>ACCIONES</span></div>
  @forelse($users as $user)
  <div class="tr"><span>{{ $user->name }}</span><span>{{ $user->username }}</span><span>{{ $user->email }}</span><span>{{ $user->role?->name??'Sin rol' }}</span><span><b class="badge {{ $user->is_active?'active':'inactive' }}">{{ $user->is_active?'Activo':'Inactivo' }}</b></span>
   <span class="action-buttons"><button type="button" onclick="document.querySelector('#edit-user-{{ $user->id }}').showModal()" title="Editar"><img src="{{ asset('assets/figma/user-edit.svg') }}" alt="Editar"></button><form method="post" action="{{ route('settings.users.destroy',$user) }}" onsubmit="return confirm('¿Eliminar este usuario?')">@csrf @method('delete')<button title="Eliminar"><img src="{{ asset('assets/figma/user-delete.svg') }}" alt="Eliminar"></button></form></span>
  </div>
  <dialog class="form-dialog" id="edit-user-{{ $user->id }}"><form method="post" action="{{ route('settings.users.update',$user) }}">@csrf @method('put')<button type="button" class="dialog-close" onclick="this.closest('dialog').close()">×</button><h2>Editar usuario</h2>@include('settings.partials.user-form',['user'=>$user])<button class="primary">Guardar cambios</button></form></dialog>
  @empty <div class="empty">No hay usuarios registrados.</div>@endforelse
 </div>
</section>
<dialog class="form-dialog" id="new-user"><form method="post" action="{{ route('settings.users.store') }}">@csrf<button type="button" class="dialog-close" onclick="this.closest('dialog').close()">×</button><h2>Nuevo Usuario</h2>@include('settings.partials.user-form',['user'=>null])<button class="primary">Crear usuario</button></form></dialog>
@endsection
