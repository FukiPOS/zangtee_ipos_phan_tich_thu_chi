<?php

namespace App\Console\Commands;

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

        // Calculate fetch range (add 1 day buffer for validation window)
        $fetchStartDate = ($startDate - 86400000 * 15);
        $fetchEndDate = ($endDate + 86400000);

        // Fetch all relevant orders and professions once
        $orderQuery = Order::where('start_date', '>', $fetchStartDate)->where('start_date', '<', $fetchEndDate);

        $allOrders = $orderQuery->get(['tran_id', 'start_date', 'store_uid', 'payment_method_id', 'payment_method_name', 'payment_amout', 'source_fb_id']);
        $allProfessions = Profession::all();

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

                    if (stripos($note, 'Quân') !== false) {
                        $professionName = 'Tiền ship từ kho';
                    } elseif (stripos($note, 'bếp') !== false) {
                        $professionName = 'Tiền ship từ bếp';
                    }

                    $profession = null;

                    if ($professionUid) {
                        $profession = $allProfessions->where('ipos_profession_uid', $professionUid)->first();
                    }

                    if (! $profession && $professionName) {
                        $profession = $allProfessions->where('name', $professionName)->first();
                    }

                    if (! $profession) {
                        // Create new
                        $isLocal = empty($professionUid) || stripos($note, 'Quân') !== false || stripos($note, 'bếp') !== false;

                        $profession = Profession::create([
                            'name' => $professionName,
                            'ipos_profession_uid' => $isLocal ? null : $professionUid,
                        ]);
                        $allProfessions->push($profession);
                    } elseif ($professionUid && $profession->ipos_profession_uid !== $professionUid) {
                        if (empty($profession->ipos_profession_uid)) {
                            $profession->update(['ipos_profession_uid' => $professionUid]);
                        }
                    }

                    $orderCode = $this->extractOrderCode($note);
                    $foundOrder = null;
                    if ($orderCode) {
                        $foundOrder = $allOrders->filter(function ($o) use ($orderCode, $startDate, $endDate) {
                            return $o->start_date >= $startDate && $o->start_date <= $endDate && (stripos($o->tran_id, $orderCode) !== false || stripos($o->source_fb_id, $orderCode) !== false);
                        })->first();

                        // Nhân viên hay  nhầm số 0 và chữ O nên phải thử
                        if (!$foundOrder) {
                            $orderCodeOto0 = str_replace("O", "0", $orderCode);
                            $foundOrder = $allOrders->filter(function ($o) use ($orderCodeOto0, $startDate, $endDate) {
                                return $o->start_date >= $startDate && $o->start_date <= $endDate && (stripos($o->tran_id, $orderCodeOto0) !== false || stripos($o->source_fb_id, $orderCodeOto0) !== false);
                            })->first(); 
                        }

                        if (!$foundOrder) {
                            $orderCode0toO = str_replace("0", "O", $orderCode);
                            $foundOrder = $allOrders->filter(function ($o) use ($orderCode0toO, $startDate, $endDate) {
                                return $o->start_date >= $startDate && $o->start_date <= $endDate && (stripos($o->tran_id, $orderCode0toO) !== false || stripos($o->source_fb_id, $orderCode0toO) !== false);
                            })->first(); 
                        }
                    }

                    $distance = $this->extractDistanceKm($note);

                    // Logic 2: Validation Flag
                    $flag = 'review';
                    if ($professionName === 'Chi phí vận chuyển') {
                        if (! $orderCode) {
                            $flag = 'review';
                        } else {
                            if ($foundOrder) {
                                $flag = $this->isValidPaymentForOrder($foundOrder, $distance) ? 'valid' : 'invalid';
                            } else {
                                $flag = 'invalid';
                            }
                        }
                    } else {
                        $tranTime = $transaction['time']; // Timestamp ms
                        if ($tranTime) {
                            $startTime = $tranTime - 86400000 * 15; // -15 day
                            $endTime = $tranTime + 86400000;   // +1 day

                            $filteredOrders = $allOrders->where('store_uid', $storeUid)
                                ->whereBetween('start_date', [$startTime, $endTime]); // Use start_date for consistency

                            foreach ($filteredOrders as $o) {
                                if ($o->tran_id) {
                                    $tid = substr($o->tran_id, -5);
                                    if ($tid && stripos($note, $tid) !== false) {
                                        $flag = 'valid';
                                        break;
                                    }
                                }
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
                            'system_flag' => $flag,

                            // Added order details
                            'order_payment_method_id' => $foundOrder ? $foundOrder->payment_method_id : null,
                            'order_payment_amount' => $foundOrder ? $foundOrder->payment_amout : null,
                            'order_payment_method_name' => $foundOrder ? ($foundOrder->payment_method_name ?? null) : null,
                            'order_distance' => $distance,
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->error("Failed to crawl store {$storeName}: {$e->getMessage()}");
            }
        }
    }

    public function extractOrderCode(string $note): ?string
    {
        $match = "";
        if (preg_match('/\b[A-Z0-9_]{5,}\b/', $note, $matches)) {
            $match = $matches[0];
        }

        if (strlen($match) > 0) {
            $match = str_replace('#', '', $match);
        }

        // Nếu độ dài >= 5 và có chứa "_" thì chỉ lấy 5 ký tự đầu
        if (strlen($match) >= 5 && strpos($match, '_') !== false) {
            $match = substr($match, 0, 5);
        }

        if (strlen($match) > 0) {
            echo $match;
            echo "\n";
            return $match;
        }

        return null;
    }

    public function isValidPaymentForOrder($order, $distance)
    {
        if (! $order) {
            return false;
        }

        $orderTranId = $order->tran_id;
        $orderPaymentAmount = $order->payment_amout;
        $orderPaymentMethodId = $order->payment_method_id;
        $orderPaymentMethodName = $order->payment_method_name;

        if ($distance <= 3 && $orderPaymentAmount > 199000) {
            return true;
        }

        if ($orderPaymentMethodId == 'MOMO_QR_AIO') {
            return true;
        }

        return false;
    }

    public function extractDistanceKm(string $note): ?float
    {
        $note = strtolower($note);

        // Bắt số đứng trước "km"
        if (! preg_match('/(\d+(?:[.,]\d+)?)\s*km/', $note, $m)) {
            return null;
        }

        // Chuẩn hóa: , -> .
        $raw = str_replace(',', '.', $m[1]);
        $km = (float) $raw;

        // Chặn giá trị vô lý (3.485 => 3485 sẽ bị loại)
        if ($km <= 0 || $km >= 20) {
            return null;
        }

        return round($km, 2);
    }
}
