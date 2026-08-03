<div class="form-grid">
 <label>Código<input name="code" value="{{ old('code',$product->code) }}" required></label>
 <label class="span-2">Descripción<input name="description" value="{{ old('description',$product->description) }}" required></label>
 <label>Unidades x caja fraccionados (opcional)<input type="number" min="1" name="units_fractioned" value="{{ old('units_fractioned',$product->units_fractioned ?: '') }}"></label>
 <label>Unidades x caja graneles<input type="number" min="0" name="units_bulk" value="{{ old('units_bulk',$product->units_bulk) }}" required><small>Usá 0 para imprimir siempre la cantidad exacta pedida.</small></label>
 <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$product->is_active))> Producto activo</label>
</div>
