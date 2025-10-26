<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Session;

class FabiHelper
{
    /**
     * Check if user is authenticated with Fabi
     */
    public static function isAuthenticated(): bool
    {
        return Session::has('fabi_token') && Session::has('fabi_auth');
    }

    /**
     * Get current authenticated user
     */
    public static function user(): ?array
    {
        return Session::get('fabi_user');
    }

    /**
     * Get user ID
     */
    public static function userId(): ?string
    {
        $user = self::user();
        return $user['id'] ?? null;
    }

    /**
     * Get user email
     */
    public static function userEmail(): ?string
    {
        $user = self::user();
        return $user['email'] ?? null;
    }

    /**
     * Get user full name
     */
    public static function userName(): ?string
    {
        $user = self::user();
        return $user['full_name'] ?? null;
    }

    /**
     * Get current company
     */
    public static function company(): ?array
    {
        return Session::get('fabi_company');
    }

    /**
     * Get company ID
     */
    public static function companyId(): ?string
    {
        $company = self::company();
        return $company['id'] ?? null;
    }

    /**
     * Get company name
     */
    public static function companyName(): ?string
    {
        $company = self::company();
        return $company['company_name'] ?? null;
    }

    /**
     * Get available brands
     */
    public static function brands(): array
    {
        return Session::get('fabi_brands', []);
    }

    /**
     * Get first brand (default brand)
     */
    public static function defaultBrand(): ?array
    {
        $brands = self::brands();
        return $brands[0] ?? null;
    }

    /**
     * Get default brand ID
     */
    public static function defaultBrandId(): ?string
    {
        $brand = self::defaultBrand();
        return $brand['id'] ?? null;
    }

    /**
     * Get available stores
     */
    public static function stores(): array
    {
        return Session::get('fabi_stores', []);
    }

    /**
     * Get active stores only
     */
    public static function activeStores(): array
    {
        return array_filter(self::stores(), function ($store) {
            return $store['active'] === 1;
        });
    }

    /**
     * Get store by ID
     */
    public static function getStore(string $storeId): ?array
    {
        $stores = self::stores();
        foreach ($stores as $store) {
            if ($store['id'] === $storeId) {
                return $store;
            }
        }
        return null;
    }

    /**
     * Get authentication token
     */
    public static function token(): ?string
    {
        return Session::get('fabi_token');
    }

    /**
     * Get full authentication data
     */
    public static function authData(): ?array
    {
        return Session::get('fabi_auth');
    }

    /**
     * Clear all Fabi session data (logout)
     */
    public static function logout(): void
    {
        Session::forget([
            'fabi_auth',
            'fabi_token',
            'fabi_user',
            'fabi_company',
            'fabi_brands',
            'fabi_stores'
        ]);
    }

    /**
     * Get user role information
     */
    public static function userRole(): ?array
    {
        $authData = self::authData();
        return $authData['user_role'] ?? null;
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole(string $roleId): bool
    {
        $userRole = self::userRole();
        return ($userRole['role_id'] ?? null) === $roleId;
    }

    /**
     * Check if user is owner
     */
    public static function isOwner(): bool
    {
        return self::hasRole('OWNER');
    }

    /**
     * Get user permissions
     */
    public static function userPermissions(): ?array
    {
        $authData = self::authData();
        return $authData['user_permissions'] ?? null;
    }
}