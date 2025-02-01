<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ItemStockRfid;
use Illuminate\Http\Request;

class ItemStockRfidController extends Controller
{
    /**
     * Get item stock RFID details by ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetails($id)
    {
        $item = ItemStockRfid::find($id);
        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Item not found.',
            ], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'Item details fetched successfully.',
            'data' => $item,
        ]);
    }
}
