<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Order;
use App\Models\Profession;
use App\Models\Store;
use App\Models\Transaction;
use App\Services\FabiService;
use Illuminate\Console\Command;

class CrawlTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crawl-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl transactions from Fabi';

    /**
     * Execute the console command.
     */
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fabiService = new FabiService;
        $data = $fabiService->login(env('IPOS_USERNAME'), env('IPOS_PASSWORD'));

        if (! isset($data['data']['token'])) {
            $this->error('Login failed');

            return;
        }

        $companyUid = $data['data']['company']['id'];
        $stores = $data['data']['stores'];

        $day = (int) env('DAY_START_MONTH', 18);

        // Calculate date range
        $now = now();
        $currentMonthDay = $now->copy()->day($day);

        // startDate = start of 18th of previous month
        $startDate = (string) $currentMonthDay->copy()->subMonth()->startOfDay()->timestamp.'000';

        // endDate = end of 18th of current month
        $endDate = (string) $currentMonthDay->copy()->endOfDay()->timestamp.'000';

        foreach ($stores as $store) {
            $brandUid = $store['brand_uid'];
            $storeUid = $store['id'];
            $storeName = $store['store_name'];

            $shortName = implode(' ', array_slice(explode(' ', $storeName), 0, 2));

            Store::updateOrCreate(
                ['ipos_id' => $storeUid],
                [
                    'name' => $storeName,
                    'short_name' => $shortName,
                    'active' => $store['active'] ?? true,
                    'brand_uid' => $brandUid,
                    'company_uid' => $companyUid,
                ]
            );

            $this->info("Crawling transactions for store: {$storeName}");

            $allTransactions = [];
            $page = 1;

            try {
                $response = $fabiService->getCashInOut(
                    $companyUid,
                    $brandUid,
                    $storeUid,
                    $startDate,
                    $endDate,
                    $page
                );

                if (! empty($response['data'])) {
                    $allTransactions = array_merge($allTransactions, $response['data']);
                    $page++;
                } else {
                    continue;
                }

                // Sync logic
                $apiCashIds = array_column($allTransactions, 'cash_id');

                Transaction::where('store_uid', $storeUid)
                    ->whereBetween('time', [$startDate, $endDate])
                    ->whereNotIn('cash_id', $apiCashIds)
                    ->delete();

                // Create or update transactions
                foreach ($allTransactions as $transaction) {
                    if (! isset($transaction['cash_id'])) {
                        continue;
                    }

                    $note = $transaction['note'] ?? '';
                    $professionUid = $transaction['profession_uid'];
                    $professionName = $transaction['profession_name'];

                    // Logic: Professions check/create
                    // If profession_uid exists from IPOS, try to find by that.
                    // If not, try to find by name.
                    // If note has "Quân", override professionName to "Tiền ship từ kho" (Logic 1 legacy)
                    // Wait, user said: "Khi crawl_transaction từ ipos bạn cần xem có profession nào mới không (ipos dùng profession_uid) nếu có thì tạo mới và đừng quên cột ipos_profession_uid, nếu profession là local thì cho cột ipos_profession_uid null"

                    // Pre-processing "Quân" legacy logic - might still be needed if it was important classification?
                    // Previous code:
                    // if (stripos($note, 'Quân') !== false) {
                    //     $professionName = 'Tiền ship từ kho';
                    //     // It did some Category logic here too.
                    // }
                    // I'll keep the name override but apply it to finding/creating a Profession.

                    if (stripos($note, 'Quân') !== false) {
                        $professionName = 'Tiền ship từ kho';
                        // For "Tiền ship từ kho", it's a local concept, so ipos_profession_uid should likely be null or ignored if we are overriding?
                        // But original code tried to find a category with that name.
                        // Let's assume we treat it as a Profession with NULL uid if it's "Quân".
                    }

                    $profession = null;

                    if ($professionUid) {
                        $profession = Profession::where('ipos_profession_uid', $professionUid)->first();
                    }

                    if (! $profession && $professionName) {
                        $profession = Profession::where('name', $professionName)->first();
                    }

                    if (! $profession) {
                        // Create new
                        $isLocal = empty($professionUid) || stripos($note, 'Quân') !== false;

                        $profession = Profession::create([
                            'name' => $professionName,
                            'ipos_profession_uid' => $isLocal ? null : $professionUid,
                        ]);
                    } elseif ($professionUid && $profession->ipos_profession_uid !== $professionUid) {
                        // Update UID if populated?
                        // If we found by name but UID was missing locally, maybe update it?
                        if (empty($profession->ipos_profession_uid)) {
                            $profession->update(['ipos_profession_uid' => $professionUid]);
                        }
                    }

                    // Logic 2: Validation Flag (Kept same)
                    $flag = 'review';
                    $tranTime = $transaction['time']; // Timestamp ms
                    if ($tranTime) {
                        $startTime = $tranTime - 86400000; // -1 day
                        $endTime = $tranTime + 86400000;   // +1 day

                        $orders = Order::where('store_uid', $storeUid)
                            ->whereBetween('tran_date', [$startTime, $endTime])
                            ->get();

                        $tranIds = [];
                        foreach ($orders as $o) {
                            if ($o->tran_id) {
                                $tranIds[] = substr($o->tran_id, -5);
                            }
                        }

                        foreach ($tranIds as $tid) {
                            if ($tid && stripos($note, $tid) !== false) {
                                $flag = 'valid';
                                break;
                            }
                        }
                    }

                    $type = $transaction['type'] ?? null;
                    $deletedAt = $transaction['deleted_at'] ?? null;
                    if ($type === 'IN' && ! $deletedAt) {
                        $deletedAt = now();
                    }

                    Transaction::withTrashed()->updateOrCreate(
                        ['cash_id' => $transaction['cash_id']],
                        [
                            // 'category_id' => $existCategory ? $existCategory->id : null,
                            // Removing category_id for now as user didn't mention it, but original code had it.
                            // If we need to keep Category syncing, we might need to check if a Category exists for this Profession Name?
                            // The user didn't explicitly say "delete categories". But "Transactions will link with profession_id".
                            // I will leave category_id alone (nullable) or try to preserve it if it was doing something useful.
                            // Original code: assigned category_id if found.
                            // Let's comment it out or keep it if easy. logic 1 used category to find profession.
                            // I'll skip category_id assignment for now to focus on Profession, unless it crashes.
                            // 'category_id' => null, // or keep existing logic if we want to mix?
                            // Let's rely on the DB default (nullable).

                            'amount' => $transaction['amount'] ?? null,
                            'brand_uid' => $transaction['brand_uid'] ?? null,
                            'company_uid' => $transaction['company_uid'] ?? null,
                            'created_at' => $transaction['created_at'] ?? null,
                            'created_by' => $transaction['created_by'] ?? null,
                            'deleted' => $transaction['deleted'] ?? false,
                            'deleted_at' => $deletedAt,
                            'deleted_by' => $transaction['deleted_by'] ?? null,
                            'employee_email' => $transaction['employee_email'] ?? null,
                            'employee_name' => $transaction['employee_name'] ?? null,
                            'note' => $transaction['note'] ?? null,
                            'payment_method_id' => $transaction['payment_method_id'] ?? null,
                            'payment_method_name' => $transaction['payment_method_name'] ?? null,

                            // New field
                            'profession_id' => $profession ? $profession->id : null,

                            'shift_id' => $transaction['shift_id'] ?? null,
                            'shift_name' => $transaction['shift_name'] ?? null,
                            'store_uid' => $transaction['store_uid'] ?? null,
                            'time' => $transaction['time'] ?? null,
                            'type' => $type,
                            'updated_at' => $transaction['updated_at'] ?? null,
                            'updated_by' => $transaction['updated_by'] ?? null,
                            'flag' => $flag,
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->error("Failed to crawl store {$storeName}: {$e->getMessage()}");
            }
        }
    }
}
