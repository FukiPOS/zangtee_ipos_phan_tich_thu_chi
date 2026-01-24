<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Store;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // Default: 19th of Last Month to 18th of This Month
        // E.g. If today is Jan 24, we want Dec 19 - Jan 18?
        // Or if the user just says "cycle is 19th prev to 18th this", they usually mean relative to the current calendar month.
        $now = Carbon::now();
        $defaultEndDate = $now->copy()->day(18)->endOfDay();
        $defaultStartDate = $defaultEndDate->copy()->subMonth()->addDay()->startOfDay(); // 19th of prev month

        // If query params exist, use them. Date strings YYYY-MM-DD
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $startMs = $fromDate
            ? Carbon::parse($fromDate)->startOfDay()->timestamp * 1000
            : $defaultStartDate->timestamp * 1000;

        $endMs = $toDate
            ? Carbon::parse($toDate)->endOfDay()->timestamp * 1000
            : $defaultEndDate->timestamp * 1000;

        $query = Transaction::query();

        if ($request->filled('store_uid')) {
            $query->where('store_uid', $request->store_uid);
        }

        // Time filter (column 'time' is string/bigint ms)
        $query->whereBetween('time', [$startMs, $endMs]);

        // Search Note
        if ($request->filled('search')) {
            $query->where('note', 'like', "%{$request->search}%");
        }

        // Filter by profession name
        if ($request->filled('profession_name')) {
            $query->where('profession_name', $request->profession_name);
        }

        $transactions = $query->orderBy('time', 'desc')
            ->paginate($request->input('per_page', 50))
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($transactions);
        }

        return Inertia::render('Transaction/Index', [
            'transactions' => $transactions,
            'stores' => Store::where('active', 1)->get(),
            'categories' => Category::where('used_for_local', true)->get(),
            'professions' => Transaction::query()
                ->whereNotNull('profession_name')
                ->where('profession_name', '!=', '')
                ->groupBy('profession_name')
                ->selectRaw("profession_name, count(*) as total, sum(case when flag = 'valid' then 1 else 0 end) as valid_count")
                ->orderBy('profession_name')
                ->get()
                ->map(fn ($item) => "{$item->profession_name} ({$item->valid_count}/{$item->total})"),
            'filters' => [
                'from_date' => $fromDate ?: $defaultStartDate->format('Y-m-d'),
                'to_date' => $toDate ?: $defaultEndDate->format('Y-m-d'),
                'store_uid' => $request->store_uid,
                'search' => $request->search,
                'profession_name' => $request->profession_name,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        // $id passed is likely the ID or cash_id. Let's assume cash_id or find by id.
        // Route parameter usually binds to logic. If ID is integer, use findOrFail($id).
        // If ID is string cash_id, use where.

        // Let's assume we pass ID (primary key).
        $transaction = Transaction::findOrFail($id);

        $validated = $request->validate([
            'profession_name' => 'nullable|string',
            'profession_uid' => 'nullable|string',
            'category_id' => 'nullable|integer',
            'flag' => 'required|in:valid,review,invalid',
            'review_status' => 'nullable|string',
        ]);

        // If category_id changed, we might want to sync profession_name/uid from Category model
        if (! empty($validated['category_id'])) {
            $cat = Category::find($validated['category_id']);
            if ($cat) {
                $validated['profession_name'] = $cat->name;
                $validated['profession_uid'] = $cat->ipos_id;
            }
        } elseif (! empty($validated['profession_name'])) {
            // If manual name text, maybe unassign category or find one?
            // For now, trust the controller to just update fields
        }

        $transaction->update($validated);

        return redirect()->back()->with('success', 'Transaction updated successfully');
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:transactions,id', // Assuming 'id' is primary key
            'flag' => 'required|in:valid,review,invalid',
        ]);

        Transaction::whereIn('id', $validated['ids'])->update(['flag' => $validated['flag']]);

        return redirect()->back()->with('success', count($validated['ids']).' transactions updated successfully');
    }
}
