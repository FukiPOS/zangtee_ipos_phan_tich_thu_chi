<?php

namespace App\Console\Commands;

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

        // $startDate = $fabiService->dateToTimestamp(date('Y-m-d'));
        // $endDate = $fabiService->dateToTimestamp(date('Y-m-d'), true);
        $day = (int) env('DAY_START_MONTH', 18);

        // Lấy ngày hiện tại
        $now = now();

        // Tính ngày 18 tháng hiện tại
        $currentMonthDay = $now->copy()->day($day);
        // startDate = đầu ngày 18 tháng trước
        $startDate = (string) $currentMonthDay->copy()->subMonth()->startOfDay()->timestamp.'000';

        // endDate = cuối ngày 18 tháng hiện tại
        $endDate = (string) $currentMonthDay->copy()->endOfDay()->timestamp.'000';

        foreach ($stores as $store) {
            $brandUid = $store['brand_uid'];
            $storeUid = $store['id'];
            $storeName = $store['store_name'];

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
                    return;
                }

                // Sync logic
                $apiCashIds = array_column($allTransactions, 'cash_id');

                \App\Models\Transaction::where('store_uid', $storeUid)
                    ->whereBetween('time', [$startDate, $endDate])
                    ->whereNotIn('cash_id', $apiCashIds)
                    ->delete();

                // Create or update transactions
                foreach ($allTransactions as $transaction) {
                    if (! isset($transaction['cash_id'])) {
                        continue;
                    }

                    \App\Models\Transaction::updateOrCreate(
                        ['cash_id' => $transaction['cash_id']],
                        [
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
                            'profession_name' => $transaction['profession_name'] ?? null,
                            'profession_uid' => $transaction['profession_uid'] ?? null,
                            'shift_id' => $transaction['shift_id'] ?? null,
                            'shift_name' => $transaction['shift_name'] ?? null,
                            'store_uid' => $transaction['store_uid'] ?? null,
                            'time' => $transaction['time'] ?? null,
                            'type' => $transaction['type'] ?? null,
                            'updated_at' => $transaction['updated_at'] ?? null,
                            'updated_by' => $transaction['updated_by'] ?? null,
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->error("Failed to crawl store {$storeName}: {$e->getMessage()}");
            }
        }
    }
}
