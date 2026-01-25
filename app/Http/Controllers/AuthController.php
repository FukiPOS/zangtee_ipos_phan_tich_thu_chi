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
                // Use FabiHelper to update all session data and sync stores to DB
                FabiHelper::updateAuthData($response['data']);

                // Sync user to database and store ipos_token
                $fabiUser = $response['data']['user'];
                $token = $response['data']['token'];
                
                $user = \App\Models\User::where('email', $fabiUser['email'])->first();

                if (! $user) {
                    $user = \App\Models\User::create([
                        'email' => $fabiUser['email'],
                        'name' => $fabiUser['full_name'],
                        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                        'ipos_token' => $token,
                    ]);
                } else {
                    $user->update([
                        'name' => $fabiUser['full_name'],
                        'ipos_token' => $token,
                    ]);
                }

                // Log the user in
                \Illuminate\Support\Facades\Auth::login($user);

                Log::info('User logged in successfully', [
                    'email' => $request->email,
                    'user_id' => $user->id
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
        // Try to update stores from API if possible
        try {
            $token = FabiHelper::token();
            $companyId = FabiHelper::companyId();
            $brandId = FabiHelper::defaultBrandId();

            if ($token && $companyId && $brandId) {
                $this->fabiService->setAuthToken($token);
                $response = $this->fabiService->getStores($companyId, $brandId);
                
                if (isset($response['data']) && is_array($response['data'])) {
                    FabiHelper::syncStores($response['data']);
                }
            }
        } catch (Exception $e) {
            Log::warning('Could not refresh stores in me()', ['error' => $e->getMessage()]);
        }

        $stores = \App\Helpers\FabiHelper::activeStores();

        return response()->json([
            'user' => FabiHelper::user(),
            'company' => FabiHelper::company(),
            'brands' => FabiHelper::brands(),
            'stores' => $stores,
            'isOwner' => FabiHelper::isOwner(),
            'userRole' => FabiHelper::userRole(),
        ]);
    }
}
