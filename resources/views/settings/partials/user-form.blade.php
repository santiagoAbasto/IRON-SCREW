<div class="form-grid">
 <label>Nombre completo<input name="name" value="{{ old('name',$user?->name) }}" required></label>
 <label>Usuario<input name="username" value="{{ old('username',$user?->username) }}" required></label>
 <label class="span-2">Email<input type="email" name="email" value="{{ old('email',$user?->email) }}" required></label>
 <label>Rol<select name="role_id"><option value="">Sin rol</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id',$user?->role_id)==$role->id)>{{ $role->name }}</option>@endforeach</select></label>
 <label>Contraseña {{ $user?'(opcional)':'' }}<input type="password" name="password" {{ $user?'':'required' }} minlength="6" autocomplete="new-password"><small>Mínimo 6 caracteres.</small></label>
 <label class="check span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user?->is_active??true))> Usuario activo</label>
</div>
