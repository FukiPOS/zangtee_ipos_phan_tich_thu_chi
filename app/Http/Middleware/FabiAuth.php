<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class FabiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user has Fabi authentication in session
        if (!Session::has('fabi_token') || !Session::has('fabi_auth')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập để tiếp tục.');
        }

        // Optional: Check if token is still valid (you can add token expiration logic here)
        $authData = Session::get('fabi_auth');
        if (isset($authData['token'])) {
            // You can decode JWT and check expiration here if needed
            // For now, we trust the session data
        }

        return $next($request);
    }
}