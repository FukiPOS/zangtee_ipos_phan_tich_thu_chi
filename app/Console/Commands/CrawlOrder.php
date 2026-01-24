<?php

namespace App\Console\Commands;

use App\Services\FabiService;
use Illuminate\Console\Command;

class CrawlOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crawl-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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

        $startDate = $fabiService->dateToTimestamp(date('Y-m-d'));
        $endDate = $fabiService->dateToTimestamp(date('Y-m-d'), true);

        foreach ($stores as $store) {
            $brandUid = $store['brand_uid'];
            $storeUid = $store['id'];
            $storeName = $store['store_name'];

            // Sync Store Data
            // Sync Store Data
            $shortName = implode(' ', array_slice(explode(' ', $storeName), 0, 2));

            \App\Models\Store::updateOrCreate(
                ['ipos_id' => $storeUid],
                [
                    'name' => $storeName,
                    'short_name' => $shortName,
                    'active' => $store['active'] ?? true,
                    'brand_uid' => $brandUid,
                    'company_uid' => $companyUid,
                ]
            );

            $this->info("Crawling orders for store: {$storeName}");

            $allOrders = [];
            $page = 1;

            try {
                do {
                    $sales = $fabiService->getSaleByDate(
                        $companyUid,
                        $brandUid,
                        $storeUid,
                        $startDate,
                        $endDate,
                        $page
                    );

                    if (! empty($sales['data'])) {
                        $allOrders = array_merge($allOrders, $sales['data']);
                        $page++;
                    } else {
                        break;
                    }
                } while (! empty($sales['data']));

                // Sync logic
                $apiOrderIds = array_column($allOrders, 'tran_id');

                // Delete orders that are in DB but not in API response for this period
                \App\Models\Order::where('store_uid', $storeUid)
                    ->whereBetween('start_date', [$startDate, $endDate])
                    ->whereNotIn('tran_id', $apiOrderIds)
                    ->delete();

                // Create or update orders
                foreach ($allOrders as $order) {
                    if (! isset($order['tran_id'])) {
                        continue;
                    }

                    \App\Models\Order::updateOrCreate(
                        ['tran_id' => $order['tran_id']],
                        [
                            'foodbook_order_id' => $order['foodbook_order_id'],
                            'store_uid' => $storeUid,
                            'tran_date' => $order['tran_date'] ?? null,
                            'source_fb_id' => $order['source_fb_id'] ?? null,
                            'tran_id' => $order['tran_id'] ?? null,
                            'tran_no' => $order['tran_no'] ?? null,
                            'start_date' => $order['start_date'] ?? null,
                            'amount_origin' => $order['amount_origin'] ?? 0,
                            'payment_method_id' => $order['payment_method'][0]['payment_method_id'] ?? null,
                            'payment_amout' => $order['payment_method'][0]['amount'] ?? null,
                            'raw_data' => json_encode($order),
                            'sale_note' => $order['sale_note'] ?? null,
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->error("Failed to crawl store {$storeName}: {$e->getMessage()}");
            }
        }
    }
}
