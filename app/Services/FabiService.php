<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FabiService
{
    private $baseUrl = 'https://posapi.ipos.vn/api';

    private $accessToken = '5c885b2ef8c34fb7b1d1fad11eef7bec';

    private $fabiType = 'pos-cms';

    private $clientTimezone = '25200000';

    private $authToken = null;

    /**
     * Get common headers for API requests
     */
    private function getHeaders($includeAuth = true): array
    {
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Origin' => 'https://fabi.ipos.vn',
            'Referer' => 'https://fabi.ipos.vn/',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
            'accept-language' => 'vi',
            'access_token' => $this->accessToken,
            'fabi_type' => $this->fabiType,
            'x-client-timezone' => $this->clientTimezone,
            'sec-ch-ua' => '"Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
        ];

        if ($includeAuth && $this->authToken) {
            $headers['Authorization'] = $this->authToken;
        }

        return $headers;
    }

    /**
     * Login to Fabi/iPos and get authentication token
     *
     * @throws Exception
     */
    public function login(string $email, string $password): array
    {
        try {
            // Check cache for token first
            if (\Illuminate\Support\Facades\Cache::has('fabi_token')) {
                $token = \Illuminate\Support\Facades\Cache::get('fabi_token');
                $this->authToken = $token;

                // We still returned the cached login data if available to avoid unnecessary login calls,
                // but let's reduce its lifetime or ensure it doesn't block store updates.
                // Actually, the user wants us to update stores whenever possible.
                // If we have a token, we can still call login to get fresh store data,
                // or we can just rely on the token and call getStores elsewhere.
                
                // Let's only return cached data if it's very fresh (e.g. 5 minutes)
                // Otherwise, let's proceed to login to get fresh stores/brands.
                if (\Illuminate\Support\Facades\Cache::has('fabi_login_data')) {
                   return \Illuminate\Support\Facades\Cache::get('fabi_login_data');
                }
            }

            $response = Http::withHeaders($this->getHeaders(false))
                ->post($this->baseUrl.'/accounts/v1/user/login', [
                    'email' => $email,
                    'password' => $password,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Store the auth token for future requests
                if (isset($data['data']['token'])) {
                    $this->authToken = $data['data']['token'];

                    // Cache token for 23 hours
                    \Illuminate\Support\Facades\Cache::put('fabi_token', $this->authToken, now()->addHours(23));
                    
                    // Cache full login data for only 30 minutes to ensure stores are refreshed periodically
                    \Illuminate\Support\Facades\Cache::put('fabi_login_data', $data, now()->addMinutes(30));
                }

                return $data;
            }

            throw new Exception('Login failed: '.$response->body());
        } catch (Exception $e) {
            Log::error('FabiService login error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Set authentication token manually
     */
    public function setAuthToken(string $token): void
    {
        $this->authToken = $token;
    }

    /**
     * Get stores list
     *
     * @throws Exception
     */
    public function getStores(string $companyUid, string $brandUid, int $page = 1): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl.'/mdata/v1/stores', [
                    'company_uid' => $companyUid,
                    'brand_uid' => $brandUid,
                    'page' => $page,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Get stores failed: '.$response->body());
        } catch (Exception $e) {
            Log::error('FabiService getStores error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get sale change log by store and date range
     *
     * @param  int  $startDate  Timestamp in milliseconds
     * @param  int  $endDate  Timestamp in milliseconds
     *
     * @throws Exception
     */
    public function getSaleChangeLog(
        string $companyUid,
        string $brandUid,
        string $storeUid,
        int $startDate,
        int $endDate,
        int $page = 1,
        int $storeOpenAt = 0
    ): array {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl.'/v3/pos-cms/sale-change-log', [
                    'company_uid' => $companyUid,
                    'brand_uid' => $brandUid,
                    'store_uid' => $storeUid,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'page' => $page,
                    'store_open_at' => $storeOpenAt,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Get sale change log failed: '.$response->body());
        } catch (Exception $e) {
            Log::error('FabiService getSaleChangeLog error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get cash in/out data by store and date range
     *
     * @param  int  $startDate  Timestamp in milliseconds
     * @param  int  $endDate  Timestamp in milliseconds
     *
     * @throws Exception
     */
    public function getCashInOut(
        string $companyUid,
        string $brandUid,
        string $storeUid,
        int $startDate,
        int $endDate,
        int $page = 1,
        int $storeOpenAt = 0
    ): array {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl.'/v3/pos-cms/cash-in-out', [
                    'company_uid' => $companyUid,
                    'brand_uid' => $brandUid,
                    'store_uid' => $storeUid,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'page' => $page,
                    'store_open_at' => $storeOpenAt,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Get cash in/out failed: '.$response->body());
        } catch (Exception $e) {
            Log::error('FabiService getCashInOut error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get sale by date report
     *
     * @param  int  $startDate  Timestamp in milliseconds
     * @param  int  $endDate  Timestamp in milliseconds
     *
     * @throws Exception
     */
    public function getSaleByDate(
        string $companyUid,
        string $brandUid,
        string $storeUid,
        int $startDate,
        int $endDate,
        int $page = 1,
        string $sort = 'dsc',
        int $storeOpenAt = 0
    ): array {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl.'/reports_v1/v3/pos-cms/report/sale-by-date', [
                    'company_uid' => $companyUid,
                    'brand_uid' => $brandUid,
                    'store_uid' => $storeUid,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'page' => $page,
                    'sort' => $sort,
                    'store_open_at' => $storeOpenAt,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Get sale by date failed: '.$response->body());
        } catch (Exception $e) {
            Log::error('FabiService getSaleByDate error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get revenue overview
     *
     * @param  int  $startDate  Timestamp in milliseconds
     * @param  int  $endDate  Timestamp in milliseconds
     *
     * @throws Exception
     */
    public function getRevenueOverview(
        string $companyUid,
        string $brandUid,
        string $listStoreUid,
        int $startDate,
        int $endDate
    ): array {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl.'/v1/reports/sale-summary/overview', [
                    'company_uid' => $companyUid,
                    'brand_uid' => $brandUid,
                    'list_store_uid' => $listStoreUid,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Get revenue overview failed: '.$response->body());
        } catch (Exception $e) {
            Log::error('FabiService getRevenueOverview error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper method to convert date to timestamp in milliseconds
     *
     * @param  string  $date  Date in Y-m-d format
     * @param  bool  $endOfDay  Set to true for end date to include full day
     */
    public function dateToTimestamp(string $date, bool $endOfDay = false): int
    {
        $timestamp = strtotime($date);

        if ($endOfDay) {
            $timestamp = strtotime($date.' 23:59:59');
        }

        return $timestamp * 1000; // Convert to milliseconds
    }

    /**
     * Get authentication token
     */
    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }
}
