<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Settings\ProductController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\OrderController;
use App\Models\User;
use App\Services\SessionProcessCancellation;
use Illuminate\Support\Facades\Hash;

Route::view('/', 'login')->name('login');
Route::post('/login', function (Request $request) {
    $request->validate(['usuario' => ['required'], 'password' => ['required']]);
    $user = User::where('username', $request->usuario)->where('is_active', true)->first();
    if (!$user || !Hash::check($request->password, $user->password)) {
        return back()->withErrors(['usuario' => 'Usuario o contraseña incorrectos.'])->onlyInput('usuario');
    }
    $request->session()->regenerate();
    app(SessionProcessCancellation::class)->clear($request->session()->getId());
    $request->session()->put(['iron_user' => $user->id, 'iron_role' => $user->role_id]);
    return redirect()->route('orders.index');
})->name('login.submit');

Route::get('/logout', function (Request $request, SessionProcessCancellation $cancellation) {
    $cancellation->cancel($request->session()->getId());
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

Route::middleware('iron.auth')->group(function () {
    Route::get('/ordenes', [OrderController::class,'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/ordenes/{order}', [OrderController::class,'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::post('/ordenes/{order}/actualizar-detalle', [OrderController::class,'refreshDetail'])->middleware('permission:orders.view')->name('orders.refresh-detail');
    Route::patch('/ordenes/{order}/finalizar', [OrderController::class,'finalize'])->middleware('permission:orders.manage')->name('orders.finalize');
    Route::post('/ordenes/sincronizar/contabilium', [OrderController::class,'sync'])->middleware('permission:orders.manage')->name('orders.sync');
    Route::view('/configuracion', 'settings.index')->middleware('permission:settings.view')->name('settings.index');
    Route::get('/configuracion/usuarios', [UserController::class, 'index'])->middleware('permission:users.manage')->name('settings.users');
    Route::post('/configuracion/usuarios', [UserController::class, 'store'])->middleware('permission:users.manage')->name('settings.users.store');
    Route::put('/configuracion/usuarios/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('settings.users.update');
    Route::delete('/configuracion/usuarios/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage')->name('settings.users.destroy');
    Route::get('/configuracion/roles', [RoleController::class, 'index'])->middleware('permission:roles.manage')->name('settings.roles');
    Route::post('/configuracion/roles', [RoleController::class, 'store'])->middleware('permission:roles.manage')->name('settings.roles.store');
    Route::put('/configuracion/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('settings.roles.update');
    Route::delete('/configuracion/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage')->name('settings.roles.destroy');
    Route::get('/configuracion/productos', [ProductController::class, 'index'])->middleware('permission:products.view')->name('settings.products');
    Route::get('/configuracion/productos/plantilla/granel', [ProductController::class, 'downloadBulkTemplate'])->middleware('permission:products.manage')->name('settings.products.bulk-template');
    Route::post('/configuracion/productos/importar/granel', [ProductController::class, 'importBulk'])->middleware('permission:products.manage')->name('settings.products.bulk-import');
    Route::put('/configuracion/productos/{product}', [ProductController::class, 'update'])->middleware('permission:products.manage')->name('settings.products.update');
    Route::put('/configuracion/productos/{product}/presentacion', [ProductController::class, 'updatePackaging'])->middleware('permission:products.manage')->name('settings.products.packaging');
    Route::delete('/configuracion/productos/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.manage')->name('settings.products.destroy');
});
