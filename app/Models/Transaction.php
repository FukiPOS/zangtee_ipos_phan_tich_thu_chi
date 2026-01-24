<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public $timestamps = true;

    public function profession()
    {
        return $this->belongsTo(Profession::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_uid', 'ipos_id');
    }
}
