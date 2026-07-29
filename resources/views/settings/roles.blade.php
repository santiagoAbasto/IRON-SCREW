@extends('layouts.app')
@section('content')
<div class="back-title"><a href="{{ route('settings.index') }}">‹</a><h1>Gestión de roles</h1></div>
<section class="panel">
 <div class="panel-head"><h2>Roles del Sistema</h2><button class="primary" type="button" onclick="document.querySelector('#new-role').showModal()">＋ Nuevo Rol</button></div>
 <div class="role-grid">
 @forelse($roles as $role)
  <article>
   <div class="role-actions"><button type="button" onclick="document.querySelector('#edit-role-{{ $role->id }}').showModal()" title="Editar"><img src="{{ asset('assets/figma/role-edit.svg') }}" alt="Editar"></button><form method="post" action="{{ route('settings.roles.destroy',$role) }}" onsubmit="return confirm('¿Eliminar este rol?')">@csrf @method('delete')<button title="Eliminar"><img src="{{ asset('assets/figma/role-delete.svg') }}" alt="Eliminar"></button></form></div>
   <h3>{{ $role->name }}</h3><p>{{ $role->description }}</p><hr><b>Permisos ({{ count($role->permissions) }}) · {{ $role->users_count }} usuarios</b><div>@foreach($role->permissions as $permission)<span>{{ $availablePermissions[$permission]??$permission }}</span>@endforeach</div>
  </article>
  <dialog class="form-dialog permissions-dialog" id="edit-role-{{ $role->id }}"><form method="post" action="{{ route('settings.roles.update',$role) }}">@csrf @method('put')<button type="button" class="dialog-close" onclick="this.closest('dialog').close()">×</button><h2>Editar rol y permisos</h2>@include('settings.partials.role-form',['role'=>$role])<button class="primary">Guardar rol</button></form></dialog>
 @empty <div class="empty">No hay roles registrados.</div>@endforelse
 </div>
</section>
<dialog class="form-dialog permissions-dialog" id="new-role"><form method="post" action="{{ route('settings.roles.store') }}">@csrf<button type="button" class="dialog-close" onclick="this.closest('dialog').close()">×</button><h2>Nuevo Rol</h2>@include('settings.partials.role-form',['role'=>null])<button class="primary">Crear rol</button></form></dialog>
@endsection
