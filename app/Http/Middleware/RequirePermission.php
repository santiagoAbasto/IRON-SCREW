<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission {
    public function handle(Request $request, Closure $next, string $permission): Response {
        $user=$request->attributes->get('ironUser');
        abort_unless($user?->role && in_array($permission,$user->role->permissions??[],true),403,'No tenés permiso para acceder a esta función.');
        return $next($request);
    }
}
