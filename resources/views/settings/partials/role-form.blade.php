<div class="form-grid">
 <label>Nombre del rol<input name="name" value="{{ old('name',$role?->name) }}" required></label>
 <label class="span-2">Descripción<input name="description" value="{{ old('description',$role?->description) }}"></label>
 <fieldset class="permissions span-2"><legend>Permisos</legend>@foreach($availablePermissions as $key=>$label)<label><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key,old('permissions',$role?->permissions??[])))><span>{{ $label }}</span></label>@endforeach</fieldset>
</div>
