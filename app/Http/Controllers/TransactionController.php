<?php

namespace App\Http\Controllers;


use App\Models\Store;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        // Filter by profession id
        if ($request->filled('profession_id')) {
            $query->where('profession_id', $request->profession_id);
        }

        $transactions = $query->with('profession')
            ->orderBy('time', 'desc')
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($transactions);
        }

        // Get stats for professions dropdown
        // We select profession_id, count, and join to get name for display
        $professionStats = Transaction::query()
            ->when($request->filled('store_uid'), fn ($q) => $q->where('store_uid', $request->store_uid))
            ->whereBetween('time', [$startMs, $endMs])
            ->whereNotNull('profession_id')
            ->groupBy('profession_id')
            ->selectRaw("profession_id, count(*) as total, sum(case when flag = 'valid' then 1 else 0 end) as valid_count")
            ->with('profession')
            ->get()
            ->map(function ($item) {
                $name = $item->profession ? $item->profession->name : 'Unknown';
                return [
                    'id' => $item->profession_id,
                    'label' => "{$name} ({$item->valid_count}/{$item->total})",
                ];
            })
            ->sortBy('label') // sort by name (label)
            ->values();

        return Inertia::render('Transaction/Index', [
            'transactions' => $transactions,
            'stores' => Store::where('active', 1)->get(),

            'professions' => $professionStats,
            'filters' => [
                'from_date' => $fromDate ?: $defaultStartDate->format('Y-m-d'),
                'to_date' => $toDate ?: $defaultEndDate->format('Y-m-d'),
                'store_uid' => $request->store_uid,
                'search' => $request->search,
                'profession_id' => $request->profession_id,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $validated = $request->validate([
            'profession_id' => 'nullable|exists:professions,id',

            'flag' => 'required|in:valid,review,invalid',
            'review_status' => 'nullable|string',
        ]);

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
