<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'foodbook_order_id',
        'source_fb_id',
        'store_uid',
        'tran_date',
        'tran_id',
        'tran_no',
        'start_date',
        'amount_origin',
        'payment_method_id',
        'payment_amout',
        'raw_data',
        'sale_note',
    ];
}
