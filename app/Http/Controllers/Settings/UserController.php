<?php
namespace App\Http\Controllers\Settings;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller {
    public function index() { return view('settings.users',['users'=>User::with('role')->orderBy('name')->get(),'roles'=>Role::orderBy('name')->get()]); }
    public function store(Request $request) {
        $data=$request->validate(['name'=>'required|max:255','username'=>'required|max:100|unique:users','email'=>'required|email|unique:users','password'=>'required|min:6','role_id'=>'nullable|exists:roles,id','is_active'=>'nullable|boolean']);
        $data['is_active']=$request->boolean('is_active'); User::create($data); return back()->with('success','Usuario creado correctamente.');
    }
    public function update(Request $request, User $user) {
        $data=$request->validate(['name'=>'required|max:255','username'=>['required','max:100',Rule::unique('users')->ignore($user)],'email'=>['required','email',Rule::unique('users')->ignore($user)],'password'=>'nullable|min:6','role_id'=>'nullable|exists:roles,id','is_active'=>'nullable|boolean']);
        if (empty($data['password'])) unset($data['password']); $data['is_active']=$request->boolean('is_active'); $user->update($data); return back()->with('success','Usuario actualizado.');
    }
    public function destroy(User $user) { $user->delete(); return back()->with('success','Usuario eliminado.'); }
}
