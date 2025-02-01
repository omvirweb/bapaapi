<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class UserMaster extends Authenticatable
{
    use HasFactory, HasApiTokens;
    protected $table = 'user_master';
    public $timestamps = false;
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'user_name',
        'login_username',
        'user_mobile',
        'user_type',
        'is_cad_designer',
        'default_department_id',
        'salary',
        'blood_group',
        'allow_all_accounts',
        'selected_accounts',
        'files',
        'default_user_photo',
        'status',
        'is_login',
        'socket_id',
        'otp_value',
        'otp_on_user',
        'designation',
        'aadhaar_no',
        'pan_no',
        'licence_no',
        'voter_id_no',
        'esi_no',
        'pf_no',
        'date_of_birth',
        'order_display_only_assigned_account',
        'bank_name',
        'bank_branch',
        'bank_acc_name',
        'bank_acc_no',
        'bank_ifsc',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];

    protected $hidden = [
        'user_password',
    ];
}
