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
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->error("Failed to crawl store {$storeName}: {$e->getMessage()}");
            }
        }
    }
}

/*
$fabiService->login response example:
Array
(
    [data] => Array
        (
            [user_role] => Array
                (
                    [id] => ee17a265-ef23-8b35-a426-8be5f97fa57a
                    [role_id] => ACCOUNTING
                    [role_name] => Kế toán
                    [description] => Kế toán được quyền xem báo cáo, cung cấp tài khoản để kết nối Phần Mềm Kế toán IPOS
                    [scope] => FABI
                    [scope_value] =>
                    [allow_access] => Array
                        (
                            [0] => POS_CMS
                            [1] => POS_MANAGER
                        )
                    [created_by] =>
                    [updated_by] =>
                    [deleted_by] =>
                    [created_at] => 2022-12-06T17:02:59.619Z
                    [updated_at] => 2022-12-06T17:02:59.619Z
                    [deleted_at] =>
                )

            [user_permissions] => Array
                (
                    [id] => 6d853917-fd21-4216-8e25-ecdef9eb4074
                    [user_uid] => 40cd0a2b-3be4-4df1-9b43-536edebc082f
                    [company_uid] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                    [stores] => Array
                        (
                            [7b7bb511-84dd-45d0-81ad-edde5539c22f] => Array
                                (
                                )

                        )

                    [tables] => Array
                        (
                        )

                    [created_by] => tranhoanggiang5@gmail.com
                    [updated_by] =>
                    [deleted_by] =>
                    [created_at] => 2025-11-19T21:17:39.803Z
                    [updated_at] => 2025-11-19T21:17:39.803Z
                    [deleted_at] =>
                )

            [company] => Array
                (
                    [id] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                    [company_id] => YV7LYX4DV4AL
                    [company_name] => fabi_zangbaby68@gmail.com
                    [description] =>
                    [extra_data] => Array
                        (
                            [phone_number] => 84969546294
                            [module] => Array
                                (
                                    [0] => menu
                                    [1] => ivt
                                    [2] => hrm
                                )

                        )

                    [active] => 1
                    [revision] => 1662519785
                )

            [brands] => Array
                (
                    [0] => Array
                        (
                            [id] => 7b7bb511-84dd-45d0-81ad-edde5539c22f
                            [brand_id] => BRAND-6TMC
                            [brand_name] => ZangTee - Drinks & More
                            [extra_data] => Array
                                (
                                    [report_config] => Array
                                        (
                                            [expanded_revenue_formula] => Array
                                                (
                                                    [0] => EXCLUDE_SHIP
                                                )

                                        )

                                )

                            [active] => 1
                            [is_fabi] => 1
                            [sort] => 1000
                            [created_at] => 1662524024
                            [currency] => VND
                        )

                )

            [cities] => Array
                (
                    [0] => Array
                        (
                            [id] => ce8ca87c-e4b9-402a-8acc-3136f6bcf42d
                            [city_id] => VNN.HANOI
                            [city_name] => Hà Nội
                            [active] => 1
                        )

                    [1] => Array
                        (
                            [id] => bbb9505a-d10d-4eb6-9f6a-6f926f0796b4
                            [city_id] => VNN.THAIBINH
                            [city_name] => Thái Bình
                            [active] => 1
                        )

                )

            [stores] => Array
                (
                    [0] => Array
                        (
                            [id] => b252a82f-73e5-47b4-94c0-0a34daae2549
                            [brand_uid] => 7b7bb511-84dd-45d0-81ad-edde5539c22f
                            [city_uid] => ce8ca87c-e4b9-402a-8acc-3136f6bcf42d
                            [company_uid] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                            [store_id] => ZBDVXWWWXWV6
                            [store_name] => ZangTee - 111 Láng Hạ
                            [logo] => https://image.foodbook.vn/images/20240107/1704594469132-ZangTee---Logo-only.png
                            [background] => https://image.foodbook.vn/images/20240107/1704594493726-310614738_422883246622171_4311411889630146092_n.jpg
                            [facebook] => https://www.facebook.com/zangteevn
                            [website] => https://zangtee.vn
                            [fb_store_id] => 57983
                            [phone] => 0968407166
                            [address] => 111 Láng Hạ, Lang Ha, Đống Đa, Hà Nội, Việt Nam
                            [latitude] => 105.812383
                            [active] => 1
                            [open_at] => 0
                            [expiry_date] => 1791997199
                            [sort] => 1000
                            [enable_change_item_in_store] => 0
                            [enable_change_item_type_in_store] => 0
                            [enable_change_printer_position_in_store] => 0
                            [enable_turn_order_report] => 1
                            [sale_change_vat_enable] => 2
                            [bill_template] => 0
                            [is_franchise] => 0
                            [tracking_sale] => 1
                            [dateend] => 2026-10-14 23:59:59
                            [istrial] => 0
                            [change_log_detail] => 1
                            [partner_id] => IPOSINVOICE
                        )

                    [1] => Array
                        (
                            [id] => 96da0392-e0de-4575-a83c-82412d554812
                            [brand_uid] => 7b7bb511-84dd-45d0-81ad-edde5539c22f
                            [city_uid] => ce8ca87c-e4b9-402a-8acc-3136f6bcf42d
                            [company_uid] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                            [store_id] => RLAEK6PPP6D7
                            [store_name] => ZangTee - 212 Lê Trọng Tấn
                            [logo] => https://image.foodbook.vn/images/20240107/1704594458678-ZangTee---Logo-only.png
                            [background] => https://image.foodbook.vn/images/20240107/1704594502735-310614738_422883246622171_4311411889630146092_n.jpg
                            [facebook] =>
                            [website] =>
                            [fb_store_id] => 68442
                            [phone] => 0368078968
                            [address] => 212 Phố Lê Trọng Tấn, Phường Khương Mai, Quận Thanh Xuân, Hà Nội, Việt Nam
                            [latitude] => 105.831832
                            [active] => 1
                            [open_at] => 0
                            [expiry_date] => 1781024399
                            [sort] => 1000
                            [enable_change_item_in_store] => 0
                            [enable_change_item_type_in_store] => 0
                            [enable_change_printer_position_in_store] => 0
                            [enable_turn_order_report] => 1
                            [sale_change_vat_enable] => 2
                            [bill_template] => 0
                            [is_franchise] => 0
                            [tracking_sale] => 1
                            [dateend] => 2026-06-09 23:59:59
                            [istrial] => 0
                            [change_log_detail] => 1
                            [partner_id] => IPOSINVOICE
                        )

                    [2] => Array
                        (
                            [id] => 82edbf77-f9d8-454a-be63-9ff78d7c9f9e
                            [brand_uid] => 7b7bb511-84dd-45d0-81ad-edde5539c22f
                            [city_uid] => ce8ca87c-e4b9-402a-8acc-3136f6bcf42d
                            [company_uid] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                            [store_id] => PRQOJYQ9J7DD
                            [store_name] => ZangTee - 7A ngõ 58 Khúc Thừa Dụ
                            [logo] => https://image.foodbook.vn/images/20240107/1704594449754-ZangTee---Logo-only.png
                            [background] => https://image.foodbook.vn/images/20240107/1704594521238-310614738_422883246622171_4311411889630146092_n.jpg
                            [facebook] =>
                            [website] =>
                            [fb_store_id] => 69841
                            [phone] => 0339078968
                            [address] => 7 Ngõ 58 P. Khúc Thừa Dụ, Dịch Vọng, Cầu Giấy, Hà Nội, Việt Nam
                            [latitude] => 105.7932034135
                            [active] => 1
                            [open_at] => 0
                            [expiry_date] => 1796057999
                            [sort] => 1000
                            [enable_change_item_in_store] => 0
                            [enable_change_item_type_in_store] => 0
                            [enable_change_printer_position_in_store] => 0
                            [enable_turn_order_report] => 1
                            [sale_change_vat_enable] => 2
                            [bill_template] => 0
                            [is_franchise] => 0
                            [tracking_sale] => 1
                            [dateend] => 2026-11-30 23:59:59
                            [istrial] => 0
                            [change_log_detail] => 1
                            [partner_id] => IPOSINVOICE
                        )

                    [3] => Array
                        (
                            [id] => 6887fa67-41a7-449b-9225-f3e970773789
                            [brand_uid] => 7b7bb511-84dd-45d0-81ad-edde5539c22f
                            [city_uid] => ce8ca87c-e4b9-402a-8acc-3136f6bcf42d
                            [company_uid] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                            [store_id] => X8RQK7D444ME
                            [store_name] => CƠ SỞ TEST - KO CÓ THẬT
                            [logo] =>
                            [background] =>
                            [facebook] =>
                            [website] =>
                            [fb_store_id] => 92936
                            [phone] => 123456789
                            [address] => Mà Lủng, Lũng Táo Commune, Đồng Văn District, Hà Giang Province, Vietnam
                            [latitude] => 105.2412763251
                            [active] => 0
                            [open_at] => 0
                            [expiry_date] => 1823360399
                            [sort] => 1000
                            [enable_change_item_in_store] => 1
                            [enable_change_item_type_in_store] => 0
                            [enable_change_printer_position_in_store] => 1
                            [enable_turn_order_report] => 0
                            [sale_change_vat_enable] => 0
                            [bill_template] => 0
                            [is_franchise] => 0
                            [tracking_sale] => 2
                            [dateend] => 2027-10-12 23:59:59
                            [istrial] => 0
                            [change_log_detail] => 0
                            [partner_id] =>
                        )

                    [4] => Array
                        (
                            [id] => fbc55871-1b0b-43a3-a26b-c65302b1b659
                            [brand_uid] => 7b7bb511-84dd-45d0-81ad-edde5539c22f
                            [city_uid] => bbb9505a-d10d-4eb6-9f6a-6f926f0796b4
                            [company_uid] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                            [store_id] => 0X3BLQZOX12W
                            [store_name] => CƠ SỞ THÁI BÌNH DỪNG HOẠT ĐỘNG
                            [logo] => https://image.foodbook.vn/images/20240904/1725434924819-ZangTee---Logo-only.png
                            [background] => https://image.foodbook.vn/images/20250921/1758450052666-1743101291775-Nen.jpg
                            [facebook] =>
                            [website] =>
                            [fb_store_id] => 88989
                            [phone] => 1900299292
                            [address] => Lê Quý Đôn, Thái Bình, Thái Bình Province, 06118, Vietnam
                            [latitude] => 106.34652482016
                            [active] => 0
                            [open_at] => 0
                            [expiry_date] => 1831654799
                            [sort] => 1000
                            [enable_change_item_in_store] => 1
                            [enable_change_item_type_in_store] => 0
                            [enable_change_printer_position_in_store] => 0
                            [enable_turn_order_report] => 1
                            [sale_change_vat_enable] => 0
                            [bill_template] => 0
                            [is_franchise] => 1
                            [tracking_sale] => 1
                            [dateend] => 2028-01-16 23:59:59
                            [istrial] => 0
                            [change_log_detail] => 1
                            [partner_id] =>
                        )

                )

            [genai_access] =>
            [genai_perms] => Array
                (
                    [REVENUE_ANALYSIS] =>
                    [CONTENT_WRITING] => 1
                    [FEEDBACK_REVIEW] => 1
                    [OPERATION_SUSGESTION] =>
                )

            [user] => Array
                (
                    [id] => 40cd0a2b-3be4-4df1-9b43-536edebc082f
                    [email] => auto.thuchi@zangtee.vn
                    [phone] =>
                    [full_name] => AUTO QL Thu Chi
                    [profile_image_path] =>
                    [role_uid] => ee17a265-ef23-8b35-a426-8be5f97fa57a
                    [company_uid] => c4e4c3c9-e177-4c62-844a-401d37ca1435
                    [country_id] => VN
                    [active] => 1
                    [is_verified] => 1
                    [partner_company_uid] =>
                    [fixed_account] =>
                    [created_by] => tranhoanggiang5@gmail.com
                    [updated_by] =>
                    [deleted_by] =>
                    [email_verified_at] =>
                    [phone_verified_at] =>
                    [password_updated_at] =>
                    [created_at] => 1763612259798
                    [updated_at] => 1763696696595
                    [deleted_at] =>
                    [last_login_at] => 1763696696000
                    [is_fabi] => 1
                )

            [token] => eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjQwY2QwYTJiLTNiZTQtNGRmMS05YjQzLTUzNmVkZWJjMDgyZiIsInV0IjoiNWM2MTZjMzQtMmEyOS00OTk2LWIxYTItZjNjM2U5Mjk0ZmY5IiwiZW1haWwiOiJhdXRvLnRodWNoaUB6YW5ndGVlLnZuIiwiaWF0IjoxNzYzNjk2Njk2LCJleHAiOjE3NjQzMDE0OTZ9.sLXbxiby9X5bjZFUVJGZpcRM4phex_3_OluwOZt_U8Y
        )

    [track_id] => 2025-1121-104456-0e377ed3-7427-454d-b529-f136d560a5c7
)
    */
