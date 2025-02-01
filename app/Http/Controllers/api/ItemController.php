<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Party;
use App\Models\TransactionsLog;
use App\Models\userslogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    public function itemList(Request $request)
    {
        try {
            // Log the request data
            // TransactionsLog::create([
            //     'request_data' => json_encode($request->all()),
            // ]);
            // Retrieve the api_token from the request parameters
            // $apiToken = $request->input('api_token');
            // $deviceName = $request->input('device_name') ?? 'Unknown Device';

            // Check if the api_token was provided

            // if (!$apiToken) {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => 'Authorization token not provided',
            //     ], 200); // Bad Request
            // }

            // Find the user associated with the api_token
            // $user = Userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

            // Find the user associated with the api_token and device_name in the user_tokens table

            // $tokenEntry = DB::table('user_tokens')
            //     ->whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])
            //     ->where('device_name', $deviceName)
            //     ->first();

            // Check if the token entry was found
            // if (!$tokenEntry) {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => 'Token is invalid for this device',
            //     ], 200); // Unauthorized
            // }

            // Fetch the user associated with the token entry
            // $user = userslogin::find($tokenEntry->user_id);

            // if (!$user) {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => 'Token is invalid',
            //     ], 200); // Unauthorized
            // }

            // Fetch items created by the authenticated user
            $items = DB::table('item_master')
                ->select('item_id', 'item_name')
                ->get();

            // Return the response in the desired format
            return response()->json([
                'status' => 1,
                'message' => 'Success',
                'records' => [
                    'data' => $items->map(function ($item) {
                        return [
                            'id' => $item->item_id,
                            'item_name' => $item->item_name,
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error message and stack trace
            Log::channel('custom_errorlog')->error('Error in itemList: ' . $e->getMessage(), ['exception' => $e]);

            // Optionally store the error log in the TransactionsLog table
            // TransactionsLog::create([
            //     'request_data' => json_encode($request->all()),
            //     'response_data' => json_encode(['error' => $e->getMessage()]),
            // ]);

            // Return an error response
            return response()->json([
                'status' => 0,
                'message' => 'An error occurred while processing the request',
            ], 200); // Internal Server Error
        }
    }
}
