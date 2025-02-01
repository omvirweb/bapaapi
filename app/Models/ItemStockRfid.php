<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemStockRfid extends Model
{
    use HasFactory;

    protected $table = 'item_stock_rfid';
    protected $primaryKey = 'item_stock_rfid_id';
    public $timestamps = false;

    protected $fillable = [
        'item_stock_id',
        'rfid_grwt',
        'rfid_less',
        'rfid_add',
        'rfid_ntwt',
        'rfid_tunch',
        'rfid_fine',
        'real_rfid',
        'rfid_size',
        'rfid_charges',
        'rfid_ad_id',
        'rfid_used',
        'from_relation_id',
        'from_module',
        'to_relation_id',
        'to_module',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];
}
