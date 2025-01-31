<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionsLog extends Model
{
    use HasFactory;
    protected $table = 'transactions_log';

    protected $fillable = ['user_id', 'request_data', 'api_token'];
}
