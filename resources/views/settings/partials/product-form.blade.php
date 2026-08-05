@php($usesKg = old('label_unit',$product->label_unit)==='kg')
<div class="form-grid" data-product-unit-form>
 <label>Código<input name="code" value="{{ old('code',$product->code) }}" required></label>
 <label class="span-2">Descripción<input name="description" value="{{ old('description',$product->description) }}" required></label>
 <label><span data-fractioned-unit-label>{{ $usesKg?'KG por caja fraccionada (opcional)':'Unidades x caja fraccionados (opcional)' }}</span><input type="number" min="0" step="{{ $usesKg?'0.001':'1' }}" name="units_fractioned" value="{{ old('units_fractioned',$product->units_fractioned) }}"><small data-unit-help>{{ $usesKg?'Ingresá el peso en KG. Podés usar decimales, por ejemplo 2,5.':'Usá 0 si todavía no está definido.' }}</small></label>
 <label><span data-bulk-unit-label>{{ $usesKg?'KG por caja granel':'Unidades x caja graneles' }}</span><input type="number" min="0" step="{{ $usesKg?'0.001':'1' }}" name="units_bulk" value="{{ old('units_bulk',$product->units_bulk) }}" required><small data-unit-help>{{ $usesKg?'Ingresá el peso en KG. Podés usar decimales, por ejemplo 2,5.':'Usá 0 si todavía no está definido.' }}</small></label>
 <label>Unidad de la etiqueta<select name="label_unit" data-product-unit-select required><option value="units" @selected(old('label_unit',$product->label_unit)==='units')>Unidades</option><option value="kg" @selected(old('label_unit',$product->label_unit)==='kg')>Kilogramos (KG)</option></select><small>Define la unidad de las cantidades y el texto que se imprime en la etiqueta.</small></label>
 <p class="configuration-help span-2">Si Granel queda en 0, se usará automáticamente la cantidad pedida. Cuando Granel tenga un valor, esa presentación será la que mande.</p>
 <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$product->is_active))> Producto activo</label>
</div>
