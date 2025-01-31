<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionsLog;
use App\Models\userslogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function issueItem(Request $request)
    {
        try {
            // Log the request data
            TransactionsLog::create([
                'request_data' => json_encode($request->all()),
            ]);

            // Retrieve the api_token from the request parameters
            $apiToken = $request->input('api_token');
            $deviceName = $request->input('device_name') ?? 'Unknown Device';

            // Check if the api_token was provided
            if (!$apiToken) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Authorization token not provided',
                ], 200); // Bad Request
            }

            // Find the user associated with the api_token
            // $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

            // Find the user associated with the api_token and device_name in the user_tokens table
            $tokenEntry = DB::table('user_tokens')
                ->whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])
                ->where('device_name', $deviceName)
                ->first();

            // Check if the token entry was found
            if (!$tokenEntry) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Token is invalid for this device',
                ], 200); // Unauthorized
            }

            // Fetch the user associated with the token entry
            $user = userslogin::find($tokenEntry->user_id);

            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Token is invalid',
                ], 200); // Unauthorized
            }

            // Handle party logic
            if ($request->filled('party_name') && !$request->filled('party_id')) {
                // If party_name is provided and party_id is blank, find or create the party by name
                $party = Party::firstOrCreate(
                    ['party_name' => $request->party_name],
                    ['created_by' => $user->id] // Optionally store the creator's ID
                );
            } elseif ($request->filled('party_id') && !$request->filled('party_name')) {
                // If party_id is provided and party_name is blank, find the party by ID
                $party = Party::find($request->party_id);

                // If party is not found, return an error
                if (!$party) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Invalid party_id',
                    ], 200);
                }
            } else {
                // If neither or both fields are filled, return an error
                return response()->json([
                    'status' => 0,
                    'message' => 'Provide either party_name or party_id, not both or none',
                ], 200);
            }

            // Handle item logic
            if ($request->filled('item') && !$request->filled('item_id')) {
                $itemName = strtolower($request->item);

                // Check if the item name already exists for the current user
                $item = Item::where('item_name', $itemName)
                    ->where('created_by', $user->id)
                    ->first();

                if (!$item) {
                    // Create a new item if it doesn't already exist for the user
                    $item = Item::create([
                        'item_name' => $itemName,
                        'created_by' => $user->id,
                    ]);
                }
            } elseif ($request->filled('item_id') && !$request->filled('item')) {
                $item = Item::find($request->item_id);

                if (!$item) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Invalid item_id',
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Provide either item or item_id, not both or none',
                ], 200);
            }

            // Check if an ID is provided for update
            if ($request->filled('id')) {
                // Find the existing transaction by ID and update it
                $transaction = Transaction::find($request->id);

                if (!$transaction) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Transaction not found',
                    ], 200);
                }

                // Update the existing transaction record
                $transaction->update([
                    'party_id' => $party->id,
                    'item' => $item->id,
                    'weight' => $request->weight ?? 0,
                    'less' => $request->less ?? 0,
                    'add' => $request->add ?? 0,
                    'net_wt' => $request->net_wt ?? 0,
                    'touch' => $request->touch ?? 0,
                    'wastage' => $request->wastage ?? 0,
                    'fine' => $request->fine ?? 0,
                    'date' => $request->date ?? $transaction->date,
                    'note' => $request->input('note', '') !== '' ? $request->input('note', '') : '',
                    'type' => 'issue',
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Record updated successfully',
                    'data' => [
                        'transaction' => $transaction,
                        'party' => $party,
                        'item' => $item,
                    ],
                ]);
            } else {
                // Create a new transaction if ID is not provided
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'party_id' => $party->id, // Store the party_id in items table
                    'item' => $item->id,
                    'weight' => $request->weight ?? 0,
                    'less' => $request->less ?? 0,
                    'add' => $request->add ?? 0,
                    'net_wt' => $request->net_wt ?? 0,
                    'touch' => $request->touch ?? 0,
                    'wastage' => $request->wastage ?? 0,
                    'fine' => $request->fine ?? 0,
                    'date' => $request->date,
                    'note' => $request->note,
                    'type' => 'issue',
                ]);
            }

            // Return the appropriate response
            return response()->json([
                'status' => 1,
                'message' => 'Record created successfully',
                'data' => [
                    'transaction' => $transaction,
                    'party' => $party,
                    'item' => $item,
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error message and stack trace
            Log::channel('custom_errorlog')->error('Error in issueItem: ' . $e->getMessage(), ['exception' => $e]);

            // Store the error log in the TransactionsLog table
            // TransactionsLog::create([
            //     'request_data' => json_encode($request->all()),
            //     'response_data' => json_encode(['error' => $e->getMessage()]), // You can log more details if needed
            // ]);

            // Return an error response
            return response()->json([
                'status' => 0,
                'message' => 'An error occurred while processing the request',
            ], 200); // Internal Server Error
        }
    }

    public function receiveItem(Request $request)
    {
        try {
            // Log the request data
            TransactionsLog::create([
                'request_data' => json_encode($request->all()),
            ]);

            // Retrieve the api_token from the request parameters
            $apiToken = $request->input('api_token');
            $deviceName = $request->input('device_name') ?? 'Unknown Device';

            // Check if the api_token was provided
            if (!$apiToken) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Authorization token not provided',
                ], 200); // Bad Request
            }

            // Find the user associated with the api_token
            // $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

            // Find the user associated with the api_token and device_name in the user_tokens table
            $tokenEntry = DB::table('user_tokens')
                ->whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])
                ->where('device_name', $deviceName)
                ->first();

            // Check if the token entry was found
            if (!$tokenEntry) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Token is invalid for this device',
                ], 200); // Unauthorized
            }

            // Fetch the user associated with the token entry
            $user = userslogin::find($tokenEntry->user_id);

            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Token is invalid',
                ], 200); // Unauthorized
            }

            // Handle party logic
            if ($request->filled('party_name') && !$request->filled('party_id')) {
                // If party_name is provided and party_id is blank, find or create the party by name
                $party = Party::firstOrCreate(
                    ['party_name' => $request->party_name],
                    ['created_by' => $user->id] // Optionally store the creator's ID
                );
            } elseif ($request->filled('party_id') && !$request->filled('party_name')) {
                // If party_id is provided and party_name is blank, find the party by ID
                $party = Party::find($request->party_id);

                // If party is not found, return an error
                if (!$party) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Invalid party_id',
                    ], 200);
                }
            } else {
                // If neither or both fields are filled, return an error
                return response()->json([
                    'status' => 0,
                    'message' => 'Provide either party_name or party_id, not both or none',
                ], 200);
            }

            // Handle item logic
            if ($request->filled('item') && !$request->filled('item_id')) {
                $itemName = strtolower($request->item);

                // Check if the item name already exists for the current user
                $item = Item::where('item_name', $itemName)
                    ->where('created_by', $user->id)
                    ->first();

                if (!$item) {
                    // Create a new item if it doesn't already exist for the user
                    $item = Item::create([
                        'item_name' => $itemName,
                        'created_by' => $user->id,
                    ]);
                }
            } elseif ($request->filled('item_id') && !$request->filled('item')) {
                $item = Item::find($request->item_id);

                if (!$item) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Invalid item_id',
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Provide either item or item_id, not both or none',
                ], 200);
            }

            // Check if an ID is provided for update
            if ($request->filled('id')) {
                // Find the existing transaction by ID and update it
                $transaction = Transaction::find($request->id);

                if (!$transaction) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Transaction not found',
                    ], 200);
                }

                // Update the existing transaction record
                $transaction->update([
                    'party_id' => $party->id,
                    'item' => $item->id,
                    'weight' => $request->weight ?? 0,
                    'less' => $request->less ?? 0,
                    'add' => $request->add ?? 0,
                    'net_wt' => $request->net_wt ?? 0,
                    'touch' => $request->touch ?? 0,
                    'wastage' => $request->wastage ?? 0,
                    'fine' => $request->fine ?? 0,
                    'date' => $request->date ?? $transaction->date,
                    'note' => $request->input('note', '') !== '' ? $request->input('note', '') : '',
                    'type' => 'receive',
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Record updated successfully',
                    'data' => [
                        'transaction' => $transaction,
                        'party' => $party,
                        'item' => $item,
                    ],
                ]);
            } else {
                // Create a new transaction if ID is not provided
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'party_id' => $party->id,
                    'item' => $item->id,
                    'weight' => $request->weight ?? 0,
                    'less' => $request->less ?? 0,
                    'add' => $request->add ?? 0,
                    'net_wt' => $request->net_wt ?? 0,
                    'touch' => $request->touch ?? 0,
                    'wastage' => $request->wastage ?? 0,
                    'fine' => $request->fine ?? 0,
                    'date' => $request->date,
                    'note' => $request->note,
                    'type' => 'receive',
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Record created successfully',
                    'data' => [
                        'transaction' => $transaction,
                        'party' => $party,
                        'item' => $item,
                    ],
                ]);
            }
        } catch (\Exception $e) {
            // Log the error message and stack trace
            Log::channel('custom_errorlog')->error('Error in issueItem: ' . $e->getMessage(), ['exception' => $e]);

            // Store the error log in the TransactionsLog table
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
    public function deleteTransaction(Request $request)
    {
        try {
            // 1. Retrieve the API Token
            $authorizationHeader = $request->input('api_token');
            $deviceName = $request->input('device_name') ?? 'Unknown Device';

            if (!$authorizationHeader) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Authorization header not provided',
                ], 200); // Bad Request
            }

            $apiToken = str_replace('Bearer ', '', $authorizationHeader);

            // 2. Authenticate the User
            // $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

            // Find the user associated with the api_token and device_name in the user_tokens table
            $tokenEntry = DB::table('user_tokens')
            ->whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])
            ->where('device_name', $deviceName)
            ->first();

            // Check if the token entry was found
            if (!$tokenEntry) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Token is invalid for this device',
                ], 200); // Unauthorized
            }

            // Fetch the user associated with the token entry
            $user = userslogin::find($tokenEntry->user_id);

            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Token is invalid',
                ], 200); // Unauthorized
            }

            // 3. Retrieve the Transaction ID
            $transactionId = $request->input('transactions_id');

            if (!$transactionId) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Transaction ID not provided',
                ], 200); // Bad Request
            }

            // 4. Find the Transaction
            $transaction = Transaction::where('id', $transactionId)
                ->where('user_id', $user->id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Transaction not found or you do not have permission to delete it',
                ], 200); // Not Found or Forbidden
            }

            // 5. Delete the Transaction
            $transaction->delete();

            // 6. Return Success Response
            return response()->json([
                'status' => 1,
                'message' => 'Record Delete Success',
            ], 200); // OK
        } catch (\Exception $e) {
            // Log the error message and stack trace
            Log::channel('custom_errorlog')->error('Error in deleteTransaction: ' . $e->getMessage(), ['exception' => $e]);

            // Store the error log in the TransactionsLog table
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

    public function editTransaction(Request $request)
    {
        // 1. Retrieve the API Token
        $authorizationHeader = $request->input('api_token');
        $deviceName = $request->input('device_name') ?? 'Unknown Device';

        if (!$authorizationHeader) {
            return response()->json([
                'status' => 0,
                'message' => 'Authorization header not provided',
            ], 200); // Bad Request
        }

        $apiToken = str_replace('Bearer ', '', $authorizationHeader);

        // 2. Authenticate the User
        // $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

        // Find the user associated with the api_token and device_name in the user_tokens table
        $tokenEntry = DB::table('user_tokens')
            ->whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])
            ->where('device_name', $deviceName)
            ->first();

        // Check if the token entry was found
        if (!$tokenEntry) {
            return response()->json([
                'status' => 0,
                'message' => 'Token is invalid for this device',
            ], 200); // Unauthorized
        }

        // Fetch the user associated with the token entry
        $user = userslogin::find($tokenEntry->user_id);

        if (!$user) {
            return response()->json([
                'status' => 0,
                'message' => 'Token is invalid',
            ], 200); // Unauthorized
        }

        // 3. Retrieve the Transaction ID
        $transactionId = $request->input('transactions_id');

        if (!$transactionId) {
            return response()->json([
                'status' => 0,
                'message' => 'Transaction ID not provided',
            ], 200); // Bad Request
        }

        // 4. Find the Transaction
        $transaction = Transaction::where('id', $transactionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'status' => 0,
                'message' => 'Transaction not found or you do not have permission to edit it',
            ], 200); // Not Found or Forbidden
        }

        // 5. Handle party logic
        if ($request->filled('party_name') && !$request->filled('party_id')) {
            // If party_name is provided and party_id is blank, find or create the party by name
            $party = Party::firstOrCreate(
                ['party_name' => $request->party_name],
                ['created_by' => $user->id] // Optionally store the creator's ID
            );
        } elseif ($request->filled('party_id') && !$request->filled('party_name')) {
            // If party_id is provided and party_name is blank, find the party by ID
            $party = Party::find($request->party_id);

            // If party is not found, return an error
            if (!$party) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid party_id',
                ], 200);
            }
        } else {
            // If neither or both fields are filled, return an error
            return response()->json([
                'status' => 0,
                'message' => 'Provide either party_name or party_id, not both or none',
            ], 200);
        }

        // 6. Handle item logic
        if ($request->filled('item') && !$request->filled('item_id')) {
            // If item name is provided and item_id is blank, find or create the item by name
            $itemName = strtolower($request->item);

            $item = Item::firstOrCreate(
                ['item_name' => $itemName],
                ['created_by' => $user->id] // Optionally store the creator's ID
            );
        } elseif ($request->filled('item_id') && !$request->filled('item')) {
            // If item_id is provided and item_name is blank, find the item by ID
            $item = Item::find($request->item_id);

            // If item is not found, return an error
            if (!$item) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid item_id',
                ], 200);
            }
        } else {
            // If neither or both fields are filled, return an error
            return response()->json([
                'status' => 0,
                'message' => 'Provide either item or item_id, not both or none',
            ], 200);
        }

        // 7. Update the Transaction
        $transaction->update([
            'party_id' => $party->id, // Store the party_id in transactions table
            'item' => $item->id,
            'weight' => $request->weight ?? $transaction->weight,
            'less' => $request->less ?? $transaction->less,
            'add' => $request->add ?? $transaction->add,
            'net_wt' => $request->net_wt ?? $transaction->net_wt,
            'touch' => $request->touch ?? $transaction->touch,
            'wastage' => $request->wastage ?? $transaction->wastage,
            'fine' => $request->fine ?? $transaction->fine,
            'date' => $request->date ?? $transaction->date,
            'note' => $request->note ?? $transaction->note,
        ]);

        // 8. Return Success Response
        return response()->json([
            'status' => 1,
            'message' => 'Record Update Success',
        ], 200); // OK
    }

    // public function issueItem(Request $request)
    // {
    //     // Retrieve the api_token from the request headers
    //     // $apiToken = str_replace('Bearer ', '', $request->header('Authorization'));

    //     // Retrieve the api_token from the request parameters
    //     $apiToken = $request->input('api_token');

    //     // Check if the api_token was provided
    //     if (!$apiToken) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization token not provided',
    //         ], 200); // Bad Request
    //     }

    //     // Find the user associated with the api_token
    //     $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Token is invalid',
    //         ], 200); // Unauthorized
    //     }

    //     // Handle party logic
    //     if ($request->filled('party_name') && !$request->filled('party_id')) {
    //         // If party_name is provided and party_id is blank, find or create the party by name
    //         $party = Party::firstOrCreate(
    //             ['party_name' => $request->party_name],
    //             ['created_by' => $user->id] // Optionally store the creator's ID
    //         );
    //     } elseif ($request->filled('party_id') && !$request->filled('party_name')) {
    //         // If party_id is provided and party_name is blank, find the party by ID
    //         $party = Party::find($request->party_id);

    //         // If party is not found, return an error
    //         if (!$party) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Invalid party_id',
    //             ], 200);
    //         }
    //     } else {
    //         // If neither or both fields are filled, return an error
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Provide either party_name or party_id, not both or none',
    //         ], 200);
    //     }

    //     // Handle item logic
    //     if ($request->filled('item') && !$request->filled('item_id')) {
    //         $itemName = strtolower($request->item);

    //         // Check if the item name already exists for the current user
    //         $item = Item::where('item_name', $itemName)
    //             ->where('created_by', $user->id)
    //             ->first();

    //         if (!$item) {
    //             // Create a new item if it doesn't already exist for the user
    //             $item = Item::create([
    //                 'item_name' => $itemName,
    //                 'created_by' => $user->id,
    //             ]);
    //         }
    //     } elseif ($request->filled('item_id') && !$request->filled('item')) {
    //         $item = Item::find($request->item_id);

    //         if (!$item) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Invalid item_id',
    //             ], 200);
    //         }
    //     } else {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Provide either item or item_id, not both or none',
    //         ], 200);
    //     }

    //     // Store the item with the appropriate party_id
    //     Transaction::create([
    //         'user_id' => $user->id,
    //         'party_id' => $party->id, // Store the party_id in items table
    //         'item' => $item->id,
    //         'weight' => $request->weight ?? 0,
    //         'less' => $request->less ?? 0,
    //         'add' => $request->add ?? 0,
    //         'net_wt' => $request->net_wt ?? 0,
    //         'touch' => $request->touch ?? 0,
    //         'wastage' => $request->wastage ?? 0,
    //         'fine' => $request->fine ?? 0,
    //         'date' => $request->date,
    //         'note' => $request->note,
    //         'type' => 'issue',
    //     ]);

    //     // Return the appropriate response
    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Success',
    //         // 'party_id' => $party->id,
    //         // 'party_name' => $request->filled('party_id') ? '' : $party->party_name,
    //     ]);
    // }

    // public function receiveItem(Request $request)
    // {
    //     // // Retrieve the api_token from the request headers
    //     // $apiToken = str_replace('Bearer ', '', $request->header('Authorization'));

    //     // Retrieve the api_token from the request parameters
    //     $apiToken = $request->input('api_token');

    //     // Check if the api_token was provided
    //     if (!$apiToken) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization token not provided',
    //         ], 200); // Bad Request
    //     }

    //     // Find the user associated with the api_token
    //     $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Token is invalid',
    //         ], 200); // Unauthorized
    //     }

    //     // Handle party logic
    //     if ($request->filled('party_name') && !$request->filled('party_id')) {
    //         // Convert the item name to lowercase before storing it
    //         // $party_name = strtolower( $request->party_name);

    //         // If party_name is provided and party_id is blank, find or create the party by name
    //         $party = Party::firstOrCreate(
    //             ['party_name' =>  $request->party_name],
    //             ['created_by' => $user->id] // Optionally store the creator's ID
    //         );
    //     } elseif ($request->filled('party_id') && !$request->filled('party_name')) {
    //         // If party_id is provided and party_name is blank, find the party by ID
    //         $party = Party::find($request->party_id);

    //         // If party is not found, return an error
    //         if (!$party) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Invalid party_id',
    //             ], 200);
    //         }
    //     } else {
    //         // If neither or both fields are filled, return an error
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Provide either party_name or party_id, not both or none',
    //         ], 200);
    //     }

    //     // Handle item logic
    //     if ($request->filled('item') && !$request->filled('item_id')) {
    //         $itemName = strtolower($request->item);

    //         // Check if the item name already exists for the current user
    //         $item = Item::where('item_name', $itemName)
    //             ->where('created_by', $user->id)
    //             ->first();

    //         if (!$item) {
    //             // Create a new item if it doesn't already exist for the user
    //             $item = Item::create([
    //                 'item_name' => $itemName,
    //                 'created_by' => $user->id,
    //             ]);
    //         }
    //     } elseif ($request->filled('item_id') && !$request->filled('item')) {
    //         $item = Item::find($request->item_id);

    //         if (!$item) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Invalid item_id',
    //             ], 200);
    //         }
    //     } else {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Provide either item or item_id, not both or none',
    //         ], 200);
    //     }

    //     // Create the item entry
    //     Transaction::create([
    //         'user_id' => $user->id,
    //         'party_id' => $party->id,
    //         'item' => $item->id,
    //         'weight' => $request->weight ?? 0,
    //         'less' => $request->less ?? 0,
    //         'add' => $request->add ?? 0,
    //         'net_wt' => $request->net_wt ?? 0,
    //         'touch' => $request->touch ?? 0,
    //         'wastage' => $request->wastage ?? 0,
    //         'fine' => $request->fine ?? 0,
    //         'date' => $request->date,
    //         'note' => $request->note,
    //         'type' => 'receive',
    //     ]);

    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Success',
    //         // 'party_id' => $party->id,
    //         // 'party_name' => $request->filled('party_id') ? '' : $party->party_name,
    //     ]);
    // }
}
