<?php

use App\Services\FabiService;
use Illuminate\Support\Facades\Http;

test('getSaleByDate makes correct request', function () {
    Http::fake([
        'https://posapi.ipos.vn/api/reports_v1/v3/pos-cms/report/sale-by-date*' => Http::response(['status' => 'success'], 200),
    ]);

    $service = new FabiService();
    $service->getSaleByDate(
        companyUid: 'test-company',
        brandUid: 'test-brand',
        storeUid: 'test-store',
        startDate: 1234567890,
        endDate: 1234567899
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://posapi.ipos.vn/api/reports_v1/v3/pos-cms/report/sale-by-date?company_uid=test-company&brand_uid=test-brand&store_uid=test-store&start_date=1234567890&end_date=1234567899&page=1&sort=dsc&store_open_at=0'
            && $request->hasHeader('fabi_type', 'pos-cms');
    });
});
