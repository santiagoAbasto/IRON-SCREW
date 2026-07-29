<?php
namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IronAuthenticated {
    public function handle(Request $request, Closure $next): Response {
        $user=User::with('role')->find($request->session()->get('iron_user'));
        if(!$user || !$user->is_active) { $request->session()->forget(['iron_user','iron_role']); return redirect()->route('login'); }
        $request->attributes->set('ironUser',$user); view()->share('ironUser',$user); return $next($request);
    }
}
