<?php

namespace App\Http\Controllers;

use App\Helpers\FabiHelper;
use App\Services\FabiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class AuthController extends Controller
{
    protected $fabiService;

    public function __construct(FabiService $fabiService)
    {
        $this->fabiService = $fabiService;
    }

    /**
     * Show the login form
     */
    public function showLogin()
    {
        // Redirect to dashboard if already logged in
        if (FabiHelper::isAuthenticated()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/Login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        try {
            // Call FabiService login
            $response = $this->fabiService->login(
                $request->email,
                $request->password
            );

            // Check if login was successful
            if (isset($response['data']) && isset($response['data']['token'])) {
                // Store the entire response in session
                Session::put('fabi_auth', $response['data']);
                Session::put('fabi_token', $response['data']['token']);
                
                // Store commonly used data for easy access
                Session::put('fabi_user', $response['data']['user']);
                Session::put('fabi_company', $response['data']['company']);
                Session::put('fabi_brands', $response['data']['brands']);
                Session::put('fabi_stores', $response['data']['stores']);

                Log::info('User logged in successfully', [
                    'email' => $request->email,
                    'user_id' => $response['data']['user']['id']
                ]);

                return redirect()->route('dashboard')->with('success', 'Đăng nhập thành công!');
            }

            return back()->withErrors([
                'email' => 'Thông tin đăng nhập không chính xác.'
            ]);

        } catch (Exception $e) {
            Log::error('Login failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'email' => 'Có lỗi xảy ra khi đăng nhập. Vui lòng thử lại.'
            ])->withInput($request->only('email'));
        }
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        Log::info('User logged out', ['user_id' => FabiHelper::userId()]);
        
        FabiHelper::logout();

        return redirect()->route('login')->with('success', 'Đăng xuất thành công!');
    }



    /**
     * API method to get current user info
     */
    public function me()
    {
        return response()->json([
            'user' => FabiHelper::user(),
            'company' => FabiHelper::company(),
            'brands' => FabiHelper::brands(),
            'stores' => FabiHelper::stores(),
            'isOwner' => FabiHelper::isOwner(),
            'userRole' => FabiHelper::userRole(),
        ]);
    }
}