<?php
namespace App\Http\Controllers\Settings;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller {
    public const PERMISSIONS=['orders.view'=>'Ver Órdenes de venta','orders.manage'=>'Gestionar Órdenes','users.manage'=>'Administrar Usuarios','roles.manage'=>'Administrar Roles y permisos','products.view'=>'Ver Productos e imprimir etiquetas','products.manage'=>'Modificar e importar Productos','settings.view'=>'Ver Configuración'];
    public function index() { return view('settings.roles',['roles'=>Role::withCount('users')->orderBy('name')->get(),'availablePermissions'=>self::PERMISSIONS]); }
    public function store(Request $request) {
        $data=$request->validate(['name'=>'required|max:100|unique:roles','description'=>'nullable|max:255','permissions'=>'array','permissions.*'=>Rule::in(array_keys(self::PERMISSIONS))]); $data['permissions']=$data['permissions']??[]; Role::create($data); return back()->with('success','Rol creado correctamente.');
    }
    public function update(Request $request, Role $role) {
        $data=$request->validate(['name'=>['required','max:100',Rule::unique('roles')->ignore($role)],'description'=>'nullable|max:255','permissions'=>'array','permissions.*'=>Rule::in(array_keys(self::PERMISSIONS))]); $data['permissions']=$data['permissions']??[]; $role->update($data); return back()->with('success','Rol y permisos actualizados.');
    }
    public function destroy(Role $role) {
        if($role->users()->exists()) return back()->withErrors(['role'=>'No se puede eliminar un rol asignado a usuarios.']); $role->delete(); return back()->with('success','Rol eliminado.');
    }
}
