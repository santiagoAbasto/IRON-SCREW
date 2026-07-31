<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('settings.users', ['users' => User::with('role')->orderBy('name')->get(), 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('createUser', [
            'name' => ['required', 'max:255'],
            'username' => ['required', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
            'role_id' => ['nullable', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Ingresá el nombre completo.',
            'username.required' => 'Ingresá un nombre de usuario.',
            'username.unique' => 'Ese nombre de usuario ya está registrado.',
            'email.required' => 'Ingresá un email.',
            'email.email' => 'Ingresá un email válido.',
            'email.unique' => 'Ese email ya está registrado.',
            'password.required' => 'Ingresá una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        User::create($data);

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => 'required|max:255', 'username' => ['required', 'max:100', Rule::unique('users')->ignore($user)], 'email' => ['required', 'email', Rule::unique('users')->ignore($user)], 'password' => ['nullable', Password::min(12)->mixedCase()->numbers()], 'role_id' => 'nullable|exists:roles,id', 'is_active' => 'nullable|boolean']);
        if (empty($data['password'])) {
            unset($data['password']);
        } $data['is_active'] = $request->boolean('is_active');
        $user->update($data);

        return back()->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        if ((int) $request->session()->get('iron_user') === $user->id) {
            return back()->withErrors(['user' => 'No podés eliminar tu propio usuario mientras tenés la sesión iniciada.']);
        }
        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }
}
