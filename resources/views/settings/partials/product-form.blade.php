<div class="form-grid">
 <label>Código<input name="code" value="{{ old('code',$product->code) }}" required></label>
 <label class="span-2">Descripción<input name="description" value="{{ old('description',$product->description) }}" required></label>
 <label>Unidades x caja fraccionados (opcional)<input type="number" min="0" name="units_fractioned" value="{{ old('units_fractioned',$product->units_fractioned) }}"><small>Usá 0 si todavía no está definido.</small></label>
 <label>Unidades x caja graneles<input type="number" min="0" name="units_bulk" value="{{ old('units_bulk',$product->units_bulk) }}" required><small>Usá 0 si todavía no está definido.</small></label>
 <p class="configuration-help span-2">Si Granel queda en 0, se usará automáticamente la cantidad pedida. Cuando Granel tenga un valor, esa presentación será la que mande.</p>
 <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$product->is_active))> Producto activo</label>
</div>
