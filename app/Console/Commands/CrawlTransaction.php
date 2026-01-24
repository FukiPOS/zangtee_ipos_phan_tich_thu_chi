<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Order;
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
                    $existCategory = null;

                    // Logic 1: Preprocessing "Quân" -> "Tiền ship từ kho"
                    if (stripos($note, 'Quân') !== false) {
                        $professionName = 'Tiền ship từ kho';
                        $cat = Category::where('name', 'Tiền ship từ kho')->first();

                        if ($cat) {
                            $existCategory = $cat;
                            $professionUid = $cat->ipos_id;
                        } else {
                            $existCategory = Category::firstOrCreate(
                                ['name' => 'Tiền ship từ kho'],
                                ['used_for_local' => true]
                            );
                        }
                    } else {
                        $existCategory = Category::where('ipos_id', $professionUid)->first();
                    }

                    if (! $existCategory && $professionUid) {
                        $existCategory = Category::create([
                            'ipos_id' => $professionUid,
                            'name' => $professionName,
                            'used_for_local' => true,
                        ]);
                    }

                    // Logic 2: Validation Flag
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

                    Transaction::updateOrCreate(
                        ['cash_id' => $transaction['cash_id']],
                        [
                            'category_id' => $existCategory ? $existCategory->id : null,
                            'amount' => $transaction['amount'] ?? null,
                            'brand_uid' => $transaction['brand_uid'] ?? null,
                            'company_uid' => $transaction['company_uid'] ?? null,
                            'created_at' => $transaction['created_at'] ?? null,
                            'created_by' => $transaction['created_by'] ?? null,
                            'deleted' => $transaction['deleted'] ?? false,
                            'deleted_at' => $transaction['deleted_at'] ?? null,
                            'deleted_by' => $transaction['deleted_by'] ?? null,
                            'employee_email' => $transaction['employee_email'] ?? null,
                            'employee_name' => $transaction['employee_name'] ?? null,
                            'note' => $transaction['note'] ?? null,
                            'payment_method_id' => $transaction['payment_method_id'] ?? null,
                            'payment_method_name' => $transaction['payment_method_name'] ?? null,
                            'profession_name' => $professionName,
                            'profession_uid' => $professionUid,
                            'shift_id' => $transaction['shift_id'] ?? null,
                            'shift_name' => $transaction['shift_name'] ?? null,
                            'store_uid' => $transaction['store_uid'] ?? null,
                            'time' => $transaction['time'] ?? null,
                            'type' => $transaction['type'] ?? null,
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
