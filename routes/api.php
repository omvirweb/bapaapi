<?php

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\BalanceController;
use App\Http\Controllers\api\CommonDataController;
use App\Http\Controllers\api\ItemController;
use App\Http\Controllers\api\ItemStockRfidController;
use App\Http\Controllers\api\PartyController;
use App\Http\Controllers\api\ProductController;
use App\Http\Controllers\api\SettingController;
use App\Http\Controllers\api\TransactionController;
use Illuminate\Support\Facades\Route;

// Route::apiResource('products',ProductController::class);
// Route::apiResource('products',ProductController::class);

Route::post('/login', [AuthController::class, 'login']);

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'getProfile']);

    Route::get('/item-stock-rfid/{id}', [ItemStockRfidController::class, 'getDetails']);
});


Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

Route::post('/sell_item', [TransactionController::class, 'sellItem']);
Route::post('/receive_item', [TransactionController::class, 'receiveItem']);

Route::post('/get_pdf',[TransactionController::class, 'getPdf']);

Route::post('/fine_balance', [BalanceController::class, 'fineBalance']);
Route::post('/touchwise_balance', [BalanceController::class, 'touchwiseBalance']);
Route::post('/ledger',[BalanceController::class, 'ledgerBalance']);
Route::post('/curret_stock',[BalanceController::class, 'currentStock']);

Route::get('/party_list', [PartyController::class, 'partyList']);
Route::get('/item_list',[ItemController::class, 'itemList']);


route::post('/delete',[TransactionController::class,'deleteTransaction']);
// route::post('/edit',[TransactionController::class,'editTransaction']);

Route::post('/settings', [SettingController::class, 'settings']);
Route::post('/common_data',[CommonDataController::class, 'commonData']);
Route::post('/check_status',[CommonDataController::class, 'checkStatus']);
