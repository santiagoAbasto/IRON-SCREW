<?php
namespace App\Http\Controllers\Settings;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductBulkSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProductController extends Controller {
    public function index(Request $request) {
        $q=(string)$request->string('q'); $products=Product::query()->whereNotNull('contabilium_id')->when($q,fn($query)=>$query->where(fn($s)=>$s->where('code','like',"%{$q}%")->orWhere('description','like',"%{$q}%")))->orderBy('code')->get(); return view('settings.products',compact('products','q'));
    }
    public function updatePackaging(Request $request, Product $product) {
        abort_if($product->contabilium_id===null,404);
        $data=$request->validate(['units_fractioned'=>'nullable|numeric|min:0','units_bulk'=>'required|numeric|min:0','label_exact_order'=>'nullable|boolean']);
        $data['units_fractioned']=$request->filled('units_fractioned')?(float)$data['units_fractioned']:0;
        $data['units_fractioned_x100']=0;
        $data['label_exact_order']=$request->boolean('label_exact_order');
        $product->update($data);
        return back()->with('success',"Presentación de {$product->code} configurada correctamente.");
    }
    public function update(Request $request, Product $product) {
        abort_if($product->contabilium_id===null,404);
        $product->update($this->validated($request,$product));
        return back()->with('success','Producto actualizado correctamente.');
    }
    public function destroy(Product $product) {
        abort_if($product->contabilium_id===null,404);
        $product->delete();
        return back()->with('success','Producto eliminado del catálogo local.');
    }
    public function downloadBulkTemplate(ProductBulkSpreadsheet $spreadsheet) {
        return $spreadsheet->download();
    }
    public function importBulk(Request $request, ProductBulkSpreadsheet $spreadsheet) {
        $request->validate(['file'=>'required|file|mimes:xlsx|max:5120']);
        try {
            $result=$spreadsheet->import($request->file('file'));
        } catch(RuntimeException $e) {
            return back()->withErrors(['file'=>$e->getMessage()]);
        }
        $new=count($result['new']);
        $changed=count($result['changed']);
        $message=$result['products_updated']
            ?"Se procesó el Excel: {$new} cantidades nuevas y {$changed} cantidades modificadas."
            :'El Excel no contiene cambios respecto de las cantidades actuales.';
        return back()->with('success',$message)->with('import_report',$result);
    }
    private function validated(Request $request,Product $product): array {
        $data=$request->validate([
            'code'=>['required','max:100',Rule::unique('products')->ignore($product)],
            'description'=>'required|max:255',
            'units_fractioned'=>'nullable|numeric|min:0',
            'units_bulk'=>'required|numeric|min:0',
            'label_unit'=>['nullable',Rule::in(['units','kg'])],
            'label_exact_order'=>'nullable|boolean',
            'is_active'=>'nullable|boolean',
        ]);
        $data['units_fractioned']=$request->filled('units_fractioned')?(float)$data['units_fractioned']:0;
        $data['units_fractioned_x100']=0;
        $data['label_unit']=$data['label_unit']??$product->label_unit;
        $data['label_exact_order']=$request->boolean('label_exact_order');
        $data['is_active']=$request->boolean('is_active');
        return $data;
    }
}
