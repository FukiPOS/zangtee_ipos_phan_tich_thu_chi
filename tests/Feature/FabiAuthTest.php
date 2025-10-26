<?php

namespace Tests\Feature;

use App\Services\FabiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class FabiAuthTest extends TestCase
{
    public function test_can_view_login_page()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200);
    }

    public function test_can_submit_login_form()
    {
        // This is a mock test - in real scenario you'd mock FabiService
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Should redirect or show error - depends on actual API response
        $this->assertTrue(
            $response->isRedirect() || 
            $response->getStatusCode() === 302 || 
            $response->getStatusCode() === 422
        );
    }

    public function test_authenticated_user_redirected_from_login()
    {
        // Mock authentication session
        Session::put('fabi_token', 'mock-token');
        Session::put('fabi_auth', ['mock' => 'data']);

        $response = $this->get('/login');
        
        $response->assertRedirect('/dashboard');
    }

    public function test_can_access_dashboard_when_authenticated()
    {
        // Mock authentication session
        Session::put('fabi_token', 'mock-token');
        Session::put('fabi_auth', ['mock' => 'data']);
        Session::put('fabi_user', ['id' => '1', 'name' => 'Test User']);

        $response = $this->get('/dashboard');
        
        $response->assertStatus(200);
    }

    public function test_cannot_access_dashboard_without_auth()
    {
        $response = $this->get('/dashboard');
        
        $response->assertRedirect('/login');
    }

    public function test_can_logout()
    {
        // Mock authentication session
        Session::put('fabi_token', 'mock-token');
        Session::put('fabi_auth', ['mock' => 'data']);

        $response = $this->post('/logout');
        
        $response->assertRedirect('/login');
        $this->assertFalse(Session::has('fabi_token'));
    }
}