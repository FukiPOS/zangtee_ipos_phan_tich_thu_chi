<?php

namespace App\Http\Controllers;


use App\Models\Profession;
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

        $query = Transaction::query();

        if ($request->filled('store_uid')) {
            $query->where('store_uid', $request->store_uid);
        }

        $query->whereBetween('time', [$startMs, $endMs]);

        if ($request->filled('search')) {
            $query->where('note', 'like', "%{$request->search}%");
        }

        if ($request->filled('profession_id')) {
            $query->where('profession_id', $request->profession_id);
        }

        if ($request->filled('flag')) {
            $query->where('flag', $request->flag);
        }

        if ($request->filled('system_flag')) {
            $query->where('system_flag', $request->system_flag);
        }

        // Compute stats BEFORE adding orderBy (aggregate + orderBy is illegal in MySQL)
        $stats = (clone $query)->selectRaw("
            count(*) as total,
            sum(case when flag = 'valid' then 1 else 0 end) as valid_count,
            sum(case when flag = 'review' then 1 else 0 end) as review_count,
            sum(case when flag = 'invalid' then 1 else 0 end) as invalid_count
        ")->first();

        $transactions = $query->with('profession')
            ->orderBy('time', 'desc')
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'transactions' => $transactions,
                'stats' => $stats
            ]);
        }

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
            ->sortBy('label')
            ->values();

        return Inertia::render('Transaction/Index', [
            'transactions' => $transactions,
            'stores' => Store::where('active', 1)->get(),
            'stats' => $stats,
            'professions' => $professionStats,
            'allProfessions' => Profession::orderBy('name')->get(['id', 'name', 'type']),
            'filters' => [
                'from_date' => $fromDate ?: $defaultStartDate->format('Y-m-d'),
                'to_date' => $toDate ?: $defaultEndDate->format('Y-m-d'),
                'store_uid' => $request->store_uid,
                'search' => $request->search,
                'profession_id' => $request->profession_id,
                'flag' => $request->flag,
                'system_flag' => $request->system_flag,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_uid' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:IN,OUT',
            'note' => 'nullable|string',
            'profession_id' => 'nullable|exists:professions,id',
            'system_note' => 'nullable|string',
            'flag' => 'required|in:valid,review,invalid',
            'time' => 'nullable|numeric',
        ]);

        $time = $validated['time'] ?? (now()->timestamp * 1000);

        $transaction = Transaction::create([
            'store_uid' => $validated['store_uid'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
            'profession_id' => $validated['profession_id'] ?? null,
            'flag' => $validated['flag'],
            'time' => $time,
            'source' => 'hand',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Process cash
        $store = Store::where('ipos_id', $validated['store_uid'])->first();
        if ($store) {
            $signedAmount = $validated['type'] === 'OUT' ? -(int) $validated['amount'] : (int) $validated['amount'];
            $store->increment('cash', $signedAmount);
            $transaction->update(['cash_processed' => true, 'cash_amount' => $signedAmount]);
        }

        return redirect()->back()->with('success', 'Đã tạo khoản thu/chi thành công');
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->source === 'hand') {
            // Hand: cho phép sửa mọi field
            $validated = $request->validate([
                'store_uid' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'type' => 'required|in:IN,OUT',
                'note' => 'nullable|string',
                'profession_id' => 'nullable|exists:professions,id',
                'flag' => 'required|in:valid,review,invalid',
                'ql_note' => 'nullable|string',
            ]);

            // Tính delta cash nếu amount hoặc type thay đổi
            $oldSignedAmount = (int) $transaction->cash_amount;
            $newSignedAmount = $validated['type'] === 'OUT' ? -(int) $validated['amount'] : (int) $validated['amount'];

            $transaction->update($validated);

            if ($transaction->cash_processed && $oldSignedAmount !== $newSignedAmount) {
                $store = Store::where('ipos_id', $validated['store_uid'])->first();
                if ($store) {
                    $delta = $newSignedAmount - $oldSignedAmount;
                    $store->increment('cash', $delta);
                    $transaction->update(['cash_amount' => $newSignedAmount]);
                }
            }
        } else {
            // Crawl: chỉ cho sửa flag, profession, note
            $validated = $request->validate([
                'profession_id' => 'nullable|exists:professions,id',
                'flag' => 'required|in:valid,review,invalid',
                'ql_note' => 'nullable|string',
            ]);

            $transaction->update($validated);
        }

        return redirect()->back()->with('success', 'Cập nhật giao dịch thành công');
    }

    public function destroy($id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->source !== 'hand') {
            return redirect()->back()->with('error', 'Không thể xóa giao dịch từ hệ thống crawl');
        }

        // Hoàn trả cash
        if ($transaction->cash_processed && $transaction->cash_amount) {
            $store = Store::where('ipos_id', $transaction->store_uid)->first();
            if ($store) {
                $store->decrement('cash', $transaction->cash_amount);
            }
        }

        $transaction->forceDelete();

        return redirect()->back()->with('success', 'Đã xóa khoản thu/chi thành công');
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:transactions,id',
            'flag' => 'required|in:valid,review,invalid',
        ]);

        Transaction::whereIn('id', $validated['ids'])->update(['flag' => $validated['flag']]);

        return redirect()->back()->with('success', count($validated['ids']).' transactions updated successfully');
    }

    public function apiStore(Request $request)
    {
       
        if ($request->header('internal-token') !== 'GHVTHV1') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'store_uid' => 'required|string',
            'cash_id' => 'required|string|unique:transactions,cash_id',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:IN,OUT',
            'note' => 'nullable|string',
            'profession_id' => 'nullable|exists:professions,id',
            'flag' => 'nullable|in:valid,review,invalid',
            'time' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        
        $time = $validated['time'] ?? (int)(now()->timestamp * 1000);

        $professionId = $validated['profession_id'] ?? null;
        if ($validated['type'] === 'IN' && !$professionId) {
            $prof = Profession::firstOrCreate(
                ['name' => 'Thu tiền mặt để chi tiêu'],
                ['type' => 'IN']
            );
            $professionId = $prof->id;
        }

        $transaction = Transaction::create([
            'store_uid' => $validated['store_uid'],
            'cash_id' => $validated['cash_id'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
            'profession_id' => $professionId,
            'flag' => $validated['flag'] ?? 'valid',
            'time' => $time,
            'source' => 'hand',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $store = Store::where('ipos_id', $validated['store_uid'])->first();
        if ($store) {
            $signedAmount = $validated['type'] === 'OUT' ? -(int) $validated['amount'] : (int) $validated['amount'];
            $store->increment('cash', $signedAmount);
            $transaction->update(['cash_processed' => true, 'cash_amount' => $signedAmount]);
        }

        return response()->json(['success' => true, 'transaction' => $transaction], 200);
    }
}
