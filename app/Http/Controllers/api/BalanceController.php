<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\userslogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    public function fineBalance(Request $request)
    {
        // Retrieve the api_token from the request parameters
        $apiToken = $request->input('api_token');
        $deviceName = $request->input('device_name') ?? 'Unknown Device'; // Default to 'Unknown Device' if not provided


        // Check if the api_token was provided
        if (!$apiToken) {
            return response()->json([
                'status' => 0,
                'message' => 'Authorization token not provided',
            ], 200); // Bad Request
        }

        // Find the user associated with the api_token
        // $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();
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

        // Fetch transactions and calculate party-wise fine balance
        $fines = Transaction::where('user_id', $user->id)
            ->join('parties', 'transactions.party_id', '=', 'parties.id')
            ->select('transactions.party_id', 'parties.party_name', 'transactions.type', 'transactions.fine')
            ->get();

        // Group and calculate fine balance for each party
        $fineBalances = $fines->groupBy('party_id')->map(function ($transactions, $partyId) {
            $partyName = $transactions->first()->party_name;
            $fineBalance = $transactions->reduce(function ($carry, $transaction) {
                return $carry + ($transaction->type === 'receive' ? $transaction->fine : -$transaction->fine);
            }, 0);

            return [
                'party_id' => $partyId,
                'party_name' => $partyName,
                'fine' => number_format($fineBalance, 3),
            ];
        })->values();

        // Return the response in the desired format
        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'records' => [
                'data' => $fineBalances,
            ],
        ]);
    }

    public function ledgerBalance(Request $request)
    {
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

        // Retrieve filter parameters from the request
        $partyId = $request->input('party_id');
        $itemId = $request->input('item_id');
        $weight = $request->input('weight');
        $touch = $request->input('touch');
        $wastage = $request->input('wastage');
        $notes = $request->input('notes');
        $isChecked = $request->input('is_checked'); // New field to filter data
        $isUnchecked = $request->input('is_unchecked'); // New field to filter unchecked transactions
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $sortOrder = $request->input('sort_order', 'desc'); // Default to descending

        // Check if any filter parameters are provided
        $hasFilters = false; // Flag to check if any filters are applied

        // Initialize the query
        $query = Transaction::where('user_id', $user->id)
            ->leftJoin('items', 'transactions.item', '=', 'items.id') // Join with items table
            ->select('transactions.*', 'items.item_name'); // Select required columns

        // Apply filters if provided
        if ($partyId) {
            $query->where('party_id', $partyId);
            $hasFilters = true;
        }
        if ($itemId) {
            $query->where('item', $itemId);
            $hasFilters = true;
        }
        if ($weight) {
            $query->where('weight', $weight);
            $hasFilters = true;
        }

        if ($touch !== null) {
            // Filter by transaction ID
            $query->where('transactions.id', $touch); // Match the transaction ID
            $hasFilters = true;
        } elseif ($touch) {
            $query->where('touch', $touch);
            $hasFilters = true;
        }

        if ($wastage) {
            $query->where('wastage', $wastage);
            $hasFilters = true;
        }

        // Check if notes (transaction ID) is provided   //
        if ($notes !== null) {
            // Filter by transaction ID
            $query->where('transactions.id', $notes); // Match the transaction ID
            $hasFilters = true;
        } elseif ($notes) {
            $query->where('note', 'LIKE', '%' . $notes . '%'); // Use LIKE for partial matches
            $hasFilters = true;
        }

        // if ($isChecked !== null) {
        //     $query->where('is_checked', $isChecked); // Filtering for is_checked
        // }

        // if ($isUnchecked !== null) {
        //     $query->where('is_checked', 0); // Filter for unchecked transactions
        // }

        // Handle the is_checked filter logic
        // if ($isChecked == 1) {
        //     // If is_checked is 1, return only transactions where is_checked = 1
        //     $query->where('is_checked', 1);
        // } elseif ($isChecked == 0) {
        //     // If is_checked is 0, exclude transactions where is_checked = 1
        //     $query->where('is_checked', '!=', 1);
        // }

        // // Handle the is_unchecked filter logic
        // if ($isUnchecked == 1) {
        //     // If is_unchecked is 1, return transactions where is_checked is 0
        //     $query->where('is_checked', 0);
        // } elseif ($isUnchecked == 0) {
        //     // If is_unchecked is 0, exclude transactions where is_checked is 0
        //     $query->where('is_checked', '!=', 0);
        // }

        // Handle the is_checked and is_unchecked filter logic
        if ($isChecked !== null || $isUnchecked !== null) {
            $hasFilters = true; // At least one filter is applied
            // Handle the is_checked and is_unchecked filter logic
            if ($isChecked == 1 && $isUnchecked == 1) {
                // If both filters are set to 1, return transactions where is_checked is either 1 or 0
                $query->where(function ($q) {
                    $q->where('is_checked', 1)
                        ->orWhere('is_checked', 0);
                });
            } elseif ($isChecked == 1 && $isUnchecked == 0) {
                // If is_checked is 1, return only transactions where is_checked = 1
                $query->where('is_checked', 1);
            } elseif ($isChecked == 0 && $isUnchecked == 1) {
                // If is_unchecked is 1, return only transactions where is_checked = 0
                $query->where('is_checked', 0);
            } elseif ($isChecked == 0 && $isUnchecked == 0) {
                $query->where(function ($q) {
                    $q->where('is_checked', '!=', 1)
                        ->where('is_checked', '!=', 0);
                });
            }
        }

        if ($startDate) {
            $query->where('date', '>=', $startDate);
            $hasFilters = true;
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
            $hasFilters = true;
        }
        // Apply sorting by date (default is descending if not provided)
        $query->orderBy('date', $sortOrder);

        // Execute the query to get the transactions
        $transactions = $query->get();

        $balance = 0; // Initialize the balance

        $response = $transactions->map(function ($transaction) use (&$balance) {
            // Calculate the fine balance and update the running balance
            if ($transaction->type == 'issue') {
                $balance -= $transaction->fine; // Subtract fine if transaction is an 'issue'
            } elseif ($transaction->type == 'receive') {
                $balance += $transaction->fine; // Add fine if transaction is a 'receive'
            }

            // Format the balance to three decimal places
            $formattedBalance = number_format($balance, 3, '.', '');

            // Format the transaction data
            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'date' => $transaction->date->format('d-m-Y'), // Format the date as '01-Jul-2024'
                'gross' => $transaction->weight, // Gross is the weight
                'fine' => $transaction->fine,
                'balance' => $formattedBalance, // Running balance
                'party_id' =>  $transaction->party->id,
                'party' =>  $transaction->party->party_name,
                'item_id' => $transaction->item,
                'item' => $transaction->item_name, // Item name from the items table
                'weight' => $transaction->weight,
                'less' => $transaction->less,
                'add' => $transaction->add,
                'net_weight' => $transaction->net_wt,
                'touch' => $transaction->touch,
                'wastage' => $transaction->wastage,
                'notes' => $transaction->note,
                'is_checked' => $transaction->is_checked,
            ];
        });

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'records' => ['data' => $response],
        ]);
    }

    public function touchwiseBalance(Request $request)
    {
        // **1. Retrieve the API Token**
        $authorizationHeader = $request->input('api_token');
        $deviceName = $request->input('device_name') ?? 'Unknown Device';

        if (!$authorizationHeader) {
            return response()->json([
                'status' => 0,
                'message' => 'Authorization header not provided',
            ], 200); // Bad Request
        }

        $apiToken = str_replace('Bearer ', '', $authorizationHeader);

        // **2. Authenticate the User**
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

        // **3. Retrieve and Process Data**
        $transactions = Transaction::with('party')
            ->where('user_id', $user->id)
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'status' => 0,
                'message' => 'No records found for the user',
            ], 200); // Not Found
        }

        // Group by party_name and then by touch
        $groupedTransactions = $transactions->groupBy(['party.party_name', 'touch']);

        $responseData = [];

        foreach ($groupedTransactions as $partyName => $touchGroups) {
            $firstTransaction = $touchGroups->first()->first(); // Get the first transaction for the party

            $partyData = [
                'id'       => $firstTransaction->id,
                'party_id' => $firstTransaction->party_id,
                'party'    => $partyName,
                'entries'  => [],
            ];

            foreach ($touchGroups as $touch => $transactions) {
                // Calculate the net fine for each touch within this party
                $netFine = $transactions->reduce(function ($carry, $transaction) {
                    return $transaction->type === 'receive'
                        ? $carry + $transaction->fine
                        : $carry - $transaction->fine;
                }, 0);

                // Add the entry for this touch if the net fine is not zero
                // if ($netFine != 0) {
                $partyData['entries'][] = [
                    'touch' => $touch,
                    'fine'  => number_format($netFine, 3, '.', ''),
                ];
                // }
            }

            // Calculate total fine for this party based on the entries
            $partyData['total'] = number_format(
                array_sum(array_column($partyData['entries'], 'fine')),
                3,
                '.',
                ''
            );

            // Add the party data to the response only if there are entries
            if (!empty($partyData['entries'])) {
                $responseData[] = $partyData;
            }
        }

        // **4. Return the JSON Response**
        return response()->json([
            'status'  => 1,
            'message' => 'Touchwise balances retrieved successfully',
            'records' => [
                'data' => $responseData,
            ],
        ], 200); // OK
    }

    public function currentStock(Request $request)
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

        // 3. Retrieve and Process Data
        // Fetch all transactions for the authenticated user with related item and party data
        $transactions = Transaction::leftJoin('items', 'transactions.item', '=', 'items.id')
            ->leftJoin('parties', 'transactions.party_id', '=', 'parties.id')
            ->where('transactions.user_id', $user->id)
            ->select(
                'transactions.id',
                'items.item_name',
                'transactions.weight as gross',
                'transactions.touch',
                'transactions.fine',
                'transactions.type',  // Added to differentiate between issue and receive
                'parties.party_name'
            )
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'status' => 0,
                'message' => 'No records found for the user',
            ], 200); // Not Found
        }

        // Group transactions by item and touch, then calculate net gross and fine
        $groupedTransactions = $transactions->groupBy(function ($transaction) {
            return $transaction->item_name . '-' . $transaction->touch;
        });

        $responseData = [];

        foreach ($groupedTransactions as $key => $group) {
            $netGross = 0;
            $netFine = 0;
            $partyName = null;

            foreach ($group as $transaction) {
                // If it's an "issue", subtract; if "receive", add
                if ($transaction->type === 'issue') {
                    $netGross -= $transaction->gross;
                    $netFine -= $transaction->fine;
                } elseif ($transaction->type === 'receive') {
                    $netGross += $transaction->gross;
                    $netFine += $transaction->fine;
                }

                // Assign party name (assuming it's the same across transactions in this group)
                $partyName = $transaction->party_name;
            }

            // Prepare the final response for each group
            $responseData[] = [
                'id' => $group->first()->id,
                'item' => $group->first()->item_name,
                'gross' => number_format($netGross, 3, '.', ''),
                'touch' => number_format($group->first()->touch, 2, '.', ''),
                'fine' => number_format($netFine, 3, '.', ''),
                'party_name' => $partyName,
            ];
        }

        // Return the response
        return response()->json([
            'status' => 1,
            'message' => 'Current stock retrieved successfully',
            'records' => [
                'data' => $responseData,
            ],
        ]);
    }


    // public function ledgerBalance(Request $request)
    // {
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

    //     // Retrieve the party_id from the request
    //     $partyId = $request->input('party_id');

    //     // Query to retrieve items associated with the user
    //     // $query = Transaction::where('user_id', $user->id);

    //     // // If party_id is provided, filter by party_id
    //     // if ($partyId) {
    //     //     $query->where('party_id', $partyId);
    //     // }

    //     // // Get the filtered items
    //     // $transactions = $query->get();

    //     // Query to retrieve items associated with the user and join with the items table
    //     $query = Transaction::where('user_id', $user->id)
    //         ->when($partyId, function ($query, $partyId) {
    //             return $query->where('party_id', $partyId);
    //         })
    //         ->leftJoin('items', 'transactions.item', '=', 'items.id') // Join with items table
    //         ->select('transactions.*', 'items.item_name') // Select required columns
    //         ->get();

    //     $balance = 0; // Initialize the balance

    //     $response = $query->map(function ($transaction) use (&$balance) {
    //         // Calculate the fine balance and update the running balance
    //         // $balance += $transaction->fine;

    //         if ($transaction->type == 'issue') {
    //             $balance -= $transaction->fine; // Subtract fine if transaction is an 'issue'
    //         } elseif ($transaction->type == 'receive') {
    //             $balance += $transaction->fine; // Add fine if transaction is a 'receive'
    //         }

    //         // Format the balance to three decimal places
    //         $formattedBalance = number_format($balance, 3, '.', '');

    //         // Format the transaction data
    //         return [
    //             'id' => $transaction->id,
    //             'type' => $transaction->type,
    //             'date' => $transaction->date->format('d-m-Y'), // Format the date as '01-Jul-2024'
    //             'gross' => $transaction->weight, // Gross is the weight
    //             'fine' => $transaction->fine,
    //             'balance' => $formattedBalance, // Running balance
    //             'party_id' =>  $transaction->party->id,
    //             'party' =>  $transaction->party->party_name,
    //             'item_id' => $transaction->item,
    //             'item' => $transaction->item_name, // Item name from the items table
    //             'weight' => $transaction->weight,
    //             'less' => $transaction->less,
    //             'add' => $transaction->add,
    //             'net_weight' => $transaction->net_wt,
    //             'touch' => $transaction->touch,
    //             'wastage' => $transaction->wastage,
    //             'notes' => $transaction->note,
    //         ];
    //     });

    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Success',
    //         'records' => ['data' => $response],
    //     ]);
    // }

    // public function fineBalance(Request $request)
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
    //             // 'message' => 'User not authenticated',
    //         ], 200); // Unauthorized
    //     }

    //     // Fetch fine balance records for the authenticated user
    //     $fines = Transaction::where('user_id', $user->id)
    //         ->join('parties', 'transactions.party_id', '=', 'parties.id')
    //         ->select('transactions.id', 'transactions.party_id', 'parties.party_name', 'transactions.fine')
    //         ->get();

    //     // Return the response in the desired format
    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Success',
    //         'records' => [
    //             'data' => $fines->map(function ($transaction) {
    //                 return [
    //                     'id' => $transaction->id,
    //                     'party_id' => $transaction->party_id,
    //                     'party_name' => $transaction->party_name,
    //                     'fine' => number_format($transaction->fine, 3) // Format the fine value
    //                 ];
    //             }),
    //         ],
    //     ]);
    // }

    //     // **3. Retrieve and Process Data**

    //     // Fetch all items for the authenticated user with related party data
    //     // $transactions = Transaction::with('party') // Eager load the party relationship
    //     //     ->where('user_id', $user->id)
    //     //     ->select('id', 'item', 'weight as gross', 'touch', 'fine', 'party_id') // Select specific fields
    //     //     ->get();

    //     $transactions = Transaction::leftJoin('items', 'transactions.item', '=', 'items.id')
    //         ->leftJoin('parties', 'transactions.party_id', '=', 'parties.id')
    //         ->where('transactions.user_id', $user->id)
    //         ->select(
    //             'transactions.id',
    //             'items.item_name',
    //             'transactions.weight as gross',
    //             'transactions.touch',
    //             'transactions.fine',
    //             'parties.party_name'
    //         )
    //         ->get();

    //     if ($transactions->isEmpty()) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'No records found for the user',
    //         ], 200); // Not Found
    //     }

    //     // Prepare the response data
    //     // 'party_name' => $transaction->party ? $transaction->party->party_name : null, // Fetch party_name
    //     $responseData = $transactions->map(function ($transaction) {
    //         return [
    //             'id' => $transaction->id,
    //             'item' => $transaction->item_name,
    //             'gross' => number_format($transaction->gross, 3, '.', ''),
    //             'touch' => number_format($transaction->touch, 2, '.', ''),
    //             'fine' => number_format($transaction->fine, 3, '.', ''),
    //             'party_name' => $transaction->party_name, // Fetch party_name
    //         ];
    //     });

    //     // **4. Return the JSON Response**

    //     return response()->json([
    //         'status'  => 1,
    //         'message' => 'Current stock retrieved successfully',
    //         'records' => [
    //             'data' => $responseData,
    //         ],
    //     ], 200); // OK
    // }

    // public function touchwiseBalance(Request $request)
    // {
    //     // **1. Retrieve the API Token**
    //     // $authorizationHeader = $request->header('Authorization');

    //     // Retrieve the api_token from the request parameters
    //     $authorizationHeader = $request->input('api_token');

    //     if (!$authorizationHeader) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization header not provided',
    //         ], 200); // Bad Request
    //     }

    //     $apiToken = str_replace('Bearer ', '', $authorizationHeader);

    //     // **2. Authenticate the User**
    //     $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Token is invalid',
    //             // 'message' => 'User not authenticated or invalid token',
    //         ], 200); // Unauthorized
    //     }

    //     // **3. Retrieve and Process Data**

    //     // Fetch all items for the authenticated user
    //     $transactions = Transaction::with('party')->where('user_id', $user->id)->get();

    //     if ($transactions->isEmpty()) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'No records found for the user',
    //         ], 200); // Not Found
    //     }

    //     // Group items by 'party_name'
    //     $groupedTransactions = $transactions->groupBy('party.party_name');

    //     $responseData = [];

    //     foreach ($groupedTransactions as $partyName => $partyTransactions) {
    //         // Calculate the total fine for the party
    //         $totalFine = $partyTransactions->sum('fine');

    //         // Prepare the entries array
    //         $entries = $partyTransactions->map(function ($transaction) {
    //             return [
    //                 'touch' => $transaction->touch,
    //                 'fine'  => number_format($transaction->fine, 3, '.', ''),
    //             ];
    //         })->values()->toArray();

    //         // Append the structured data to the response
    //         $responseData[] = [
    //             'id'       => $partyTransactions->first()->id, // Using the first item's ID
    //             'party_id' => $partyTransactions->first()->party_id, // Using the first item's party_id
    //             'party'   => $partyName,
    //             'total'   => number_format($totalFine, 3, '.', ''),
    //             'entries' => $entries,
    //         ];
    //     }

    //     // **4. Return the JSON Response**

    //     return response()->json([
    //         'status'  => 1,
    //         'message' => 'Touchwise balances retrieved successfully',
    //         'records' => [
    //             'data' => $responseData,
    //         ],
    //     ], 200); // OK
    // }

    // public function ledgerBalance(Request $request)
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
    //             // 'message' => 'User not authenticated',
    //         ], 200); // Unauthorized
    //     }

    //     // Retrieve the items associated with the user
    //     // $items = Item::where('user_id', $user->id)->get();

    //     // Retrieve the party_id from the request
    //     $partyId = $request->input('party_id');

    //     if (!$partyId) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Party ID not provided',
    //         ], 400); // Bad Request
    //     }

    //     // Retrieve the items associated with the user and the party_id
    //     $items = Item::where('user_id', $user->id)
    //         ->where('party_id', $partyId)
    //         ->get();

    //     $balance = 0; // Initialize the balance

    //     $response = $items->map(function ($item) use (&$balance) {
    //         // Calculate the fine balance and update the running balance
    //         $balance += $item->fine;

    //         // Format the item data
    //         return [
    //             'type' => $item->type,
    //             'date' => $item->date->format('d-M-Y'), // Format the date as '01-Jul-2024'
    //             'gross' => $item->weight, // Gross is the weight
    //             'fine' => $item->fine,
    //             'balance' => $balance, // Running balance
    //             'party' =>  $item->party->party_name,
    //             'item' => $item->item,
    //             'weight' => $item->weight,
    //             'less' => $item->less,
    //             'add' => $item->add,
    //             'net_weight' => $item->net_wt,
    //             'touch' => $item->touch,
    //             'wastage' => $item->wastage,
    //             'notes' => $item->note,
    //         ];
    //     });

    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Success',
    //         'records' => ['data' => $response],
    //     ]);
    // }


    // public function fineBalance(Request $request)
    // {
    //     // Retrieve the api_token from the request headers
    //     $apiToken = str_replace('Bearer ', '', $request->header('Authorization'));

    //     // Check if the api_token was provided
    //     if (!$apiToken) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization token not provided',
    //         ], 400); // Bad Request
    //     }
    //     // dd($apiToken);
    //     // Find the user associated with the api_token
    //     $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'User not authenticated',
    //         ], 401); // Unauthorized
    //     }

    //     // Fetch fine balance records for the authenticated user
    //     $fines = Item::where('user_id', $user->id)->select('id', 'party_name', 'fine')->get();
    //     // Return the response in the desired format
    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Success',
    //         'records' => [
    //             'data' => $fines->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'party_name' => $item->party_name,
    //                     'fine' => number_format($item->fine, 3) // Format the fine value
    //                 ];
    //             }),
    //         ],
    //     ]);
    // }



    // public function touchwiseBalance(Request $request)
    // {
    //     // **1. Retrieve the API Token**
    //     // Extract the token from the 'Authorization' header
    //     $authorizationHeader = $request->header('Authorization');

    //     if (!$authorizationHeader) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization header not provided',
    //         ], 400); // Bad Request
    //     }

    //     // Remove 'Bearer ' prefix if present
    //     $apiToken = str_replace('Bearer ', '', $authorizationHeader);

    //     // **2. Authenticate the User**
    //     $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'User not authenticated or invalid token',
    //         ], 401); // Unauthorized
    //     }

    //     // **3. Retrieve and Process Data**

    //     // Fetch all items for the authenticated user
    //     $items = Item::where('user_id', $user->id)->get();

    //     if ($items->isEmpty()) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'No records found for the user',
    //         ], 404); // Not Found
    //     }

    //     // Group items by 'party_name'
    //     $groupedItems = $items->groupBy('party_name');

    //     $responseData = [];

    //     foreach ($groupedItems as $partyName => $partyItems) {
    //         // Calculate the total fine for the party
    //         $totalFine = $partyItems->sum('fine');

    //         // Prepare the entries array
    //         $entries = $partyItems->map(function ($item) {
    //             return [
    //                 'touch' => $item->touch,
    //                 'fine'  => number_format($item->fine, 3, '.', ''),
    //             ];
    //         })->values()->toArray();

    //         // Append the structured data to the response
    //         $responseData[] = [
    //             'party'   => $partyName,
    //             'total'   => number_format($totalFine, 3, '.', ''),
    //             'entries' => $entries,
    //         ];
    //     }

    //     // **4. Return the JSON Response**

    //     return response()->json([
    //         'status'  => 1,
    //         'message' => 'Touchwise balances retrieved successfully',
    //         'records' => [
    //             'data' => $responseData,
    //         ],
    //     ], 200); // OK
    // }

    // public function currentStock(Request $request)
    // {
    //     // Retrieve the api_token from the request headers
    //     $apiToken = str_replace('Bearer ', '', $request->header('Authorization'));

    //     // Extract the token from the 'Authorization' header
    //     $authorizationHeader = $request->header('Authorization');

    //     if (!$authorizationHeader) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization header not provided',
    //         ], 400); // Bad Request
    //     }

    //     // Remove 'Bearer ' prefix if present
    //     $apiToken = str_replace('Bearer ', '', $authorizationHeader);

    //     // **2. Authenticate the User**
    //     $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'User not authenticated or invalid token',
    //         ], 401); // Unauthorized
    //     }

    //     // Fetch the current stock list (You should replace 'Stock' with your actual model name)
    //     $stocks = Item::where('user_id', $user->id)
    //         // ->select('id', 'item', 'gross', 'touch', 'fine')
    //         ->select('id', 'item', 'weight as gross', 'touch', 'fine')
    //         ->get();

    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Success',
    //         'records' => [
    //             'data' => $stocks
    //         ],
    //     ]);
    // }


    // public function touchwiseBalance(Request $request)
    // {
    //     // Retrieve the api_token from the request headers
    //     $apiToken = str_replace('Bearer ', '', $request->header('Authorization'));

    //     // Check if the api_token was provided
    //     if (!$apiToken) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization token not provided',
    //         ], 400); // Bad Request
    //     }

    //     // Find the user associated with the api_token
    //     $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'User not authenticated',
    //         ], 401); // Unauthorized
    //     }

    //     $items = Item::where('user_id', $user->id)
    //         ->selectRaw('party_name as party, SUM(fine) as total, touch')
    //         ->groupBy('touch')
    //         ->get();

    //     $response = [];

    //     foreach ($items as $item) {
    //         $response[] = [
    //             'party' => $item->party_name,
    //             'total' => $item->fine,
    //             'entries' => Item::where('user_id', $user->id)
    //                 ->where('party_name', $item->party_name)
    //                 ->select('touch', 'fine')
    //                 ->get(),
    //         ];
    //     }

    //     return response()->json([
    //         'result' => 1,
    //         'message' => 'Success',
    //         'records' => ['data' => $response],
    //     ]);
    // }
}
