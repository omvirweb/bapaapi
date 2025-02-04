<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ItemStockRfid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemStockRfidController extends Controller
{
    /**
     * Get item stock RFID details by ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetails($id, Request $request)
    {
        $item = ItemStockRfid::find($id);
        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Item not found.',
            ], 404);
        }
        if ($request->filled('party_name') && !$request->filled('party_id')) {
            $account = DB::table('account')
                ->where('account_name', $request->party_name)
                ->first();

            if (!$account) {
                $account_id = DB::table('account')->insertGetId([
                    'account_name' => $request->party_name,
                    'account_group_id' => config('const.CUSTOMER_GROUP'),
                ]);
                $account = DB::table('account')->where('account_id', $account_id)->first();
            }
        } elseif ($request->filled('party_id')) {
            $account = DB::table('account')
                ->where('account_id', $request->party_id)
                ->first();
            if (!$account) {
                return response()->json(['status' => 0, 'message' => 'Invalid party_id'], 400);
            }
        } else {
            return response()->json(['status' => 0, 'message' => 'Party is required'], 400);
        }
        $wastage = 0;
        // Check for party item details
        $party_item_details = DB::table('party_item_details')
            ->where('account_id', $account->account_id)
            ->first();
        if ($party_item_details) {
            $wastage = $party_item_details->wstg;
        } else {
            // Get the default item details
            $item_stock = DB::table('item_stock')
                ->where('item_stock_id', $item->item_stock_id)
                ->first();
            if ($item_stock) {
                $item_master = DB::table('item_master')
                    ->where('item_id', $item_stock->item_id)
                    ->first();
                if ($item_master) {
                    $wastage = $item_master->default_wastage;
                }
            }
        }
        $item->wastage = $wastage;
        return response()->json([
            'status' => true,
            'message' => 'Item details fetched successfully.',
            'data' => $item,
        ]);
    }
}
