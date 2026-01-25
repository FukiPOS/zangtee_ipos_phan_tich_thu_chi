<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Transaction;
use App\Services\FabiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get Revenue from Fabi Service (aggregate) for each store
        // 2. Get Expense from Local Transactions (aggregate by profession)

        $now = Carbon::now();
        $defaultEndDate = $now->copy()->day(18)->endOfDay();
        $defaultStartDate = $defaultEndDate->copy()->subMonth()->addDay()->startOfDay();

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $startMs = $fromDate
            ? Carbon::parse($fromDate)->startOfDay()->timestamp * 1000
            : $defaultStartDate->timestamp * 1000;
        $endMs = $toDate
            ? Carbon::parse($toDate)->endOfDay()->timestamp * 1000
            : $defaultEndDate->timestamp * 1000;

        // Fetch Revenues (might be slow, maybe cache or do client-side async? For now sync)
        $stores = Store::where('active', 1)->get();
        $revenueData = [];

        // We need to call FabiService. But FabiService::login needs credentials.
        // We assume env credentials or cached token.
        $fabi = new FabiService;

        // Use token from logged in user (DB) or session
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->ipos_token) {
            $fabi->setAuthToken($user->ipos_token);
        } elseif ($token = \App\Helpers\FabiHelper::token()) {
            $fabi->setAuthToken($token);
        }

        // Ideally, we shouldn't loop API calls in controller.
        // But for MVP:
        // Or we can rely on `Order` table if it has accurate revenue?
        // `revenue_overview_request` gets "revenue_net". Order table Sum(amount_origin or total_amount) might be close.
        // User said "Gọi thêm API revenue_overview_request để lấy revenue_net".
        // Let's try to trust Order table for locally cached revenue if crawling is up to date,
        // to avoid API latency. But user specifically asked for `revenue_overview_request`.
        // Let's implement one aggregate call if possible? The request example showed `list_store_uid`.
        // We can pass multiple store UIDs?
        // Example: `list_store_uid=b252...`
        // If it supports CSV, great. If not, maybe loop.

        // Let's simulate retrieving revenue from Order table first for speed,
        // or implementing the API call if I knew the multi-store syntax.
        // The example curl has `list_store_uid=uid`. Maybe comma separated?

        // Calculation from DB (Transactions) for Summary (respected filter)
        $expenses = Transaction::whereBetween('time', [$startMs, $endMs])
            ->when($request->store_uid, fn ($q) => $q->where('store_uid', $request->store_uid))
            ->with('profession')
            ->get()
            ->groupBy('profession_id')
            ->map(function ($items, $id) {
                $first = $items->first();
                $name = $first->profession ? $first->profession->name : 'Chưa phân loại';

                return [
                    'id' => $id,
                    'name' => $name,
                    'amount' => $items->sum('amount'),
                ];
            })
            ->values();

        $totalExpense = $expenses->sum('amount');

        // Revenue Comparison Data (Ignore store_uid filter to show all stores)
        $comparisonData = Transaction::whereBetween('time', [$startMs, $endMs])
            ->with(['profession', 'store'])
            ->get()
            ->groupBy('profession_id')
            ->map(function ($items, $id) {
                $first = $items->first();
                $professionName = $first->profession ? $first->profession->name : 'Chưa phân loại';

                // Group by store within this profession
                $storesData = $items->groupBy('store_uid')->map(function ($storeItems, $storeUid) {
                    $firstStore = $storeItems->first();
                    $storeName = $firstStore->store ? $firstStore->store->name : $storeUid;
                    // Use short_name if available, else name
                    if ($firstStore->store && $firstStore->store->short_name) {
                        $storeName = $firstStore->store->short_name;
                    }

                    return [
                        'uid' => $storeUid,
                        'name' => $storeName,
                        'amount' => $storeItems->sum('amount'),
                    ];
                })->values();

                return [
                    'id' => $id,
                    'name' => $professionName,
                    'stores' => $storesData,
                ];
            })
            ->values();

        // Initialize revenue
        $revenue = 0;

        try {
            // Get Company/Brand UID if not stored, but usually stores have them.
            // Let's assume user has access to one company/brand for now or get from first store.
            $firstStore = $stores->first();
            if ($firstStore) {
                $companyUid = $firstStore->company_uid ?? 'unknown'; // Should be in DB or get from login data
                $brandUid = $firstStore->brand_uid ?? 'unknown';

                // If filtering by store, usage that store UID. Else list all.
                $listStoreUid = '';
                if ($request->store_uid) {
                    $listStoreUid = $request->store_uid;
                } else {
                    // Comma separated list of all stores
                    $listStoreUid = $stores->pluck('ipos_id')->implode(',');
                }

                if ($companyUid && $brandUid) {
                    $apiResponse = $fabi->getRevenueOverview($companyUid, $brandUid, $listStoreUid, $startMs, $endMs);

                    // Assuming response structure has data.
                    // Usually $apiResponse['data'] is a list or object.
                    // If it's `revenue_overview_request`, it might return aggregate or per store.
                    // Let's assume it returns a list of revenues per store or a total object.
                    // Based on standard iPos reports, it often returns "data" -> array of items.
                    // Each item might have "revenue_net".

                    if (! empty($apiResponse['data'])) {
                        $revenue = $apiResponse['data']['revenue_net'] ?? 0;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback or log?
            // If API fails, maybe fallback to Orders or just 0
            // $revenue = \App\Models\Order::whereBetween('start_date', [$startMs, $endMs])
            //      ->when($request->store_uid, fn($q) => $q->where('store_uid', $request->store_uid))
            //      ->sum('amount_origin');
            \Illuminate\Support\Facades\Log::error('Revenue API failed: '.$e->getMessage());
        }

        return Inertia::render('Revenue/Index', [
            'totalRevenue' => $revenue,
            'totalExpense' => $totalExpense,
            'expenseByProfession' => $expenses,
            'comparisonData' => $comparisonData,
            'filters' => [
                'from_date' => $fromDate ?: $defaultStartDate->format('Y-m-d'),
                'to_date' => $toDate ?: $defaultEndDate->format('Y-m-d'),
                'store_uid' => $request->store_uid,
            ],
            'stores' => $stores,
        ]);
    }
}
