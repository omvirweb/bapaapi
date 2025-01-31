<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Party;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionsLog;
use App\Models\userslogin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommonDataController extends Controller
{
    public function commonData(Request $request)
    {
        try {
            // Retrieve the api_token and device_name from the request parameters
            $apiToken = $request->input('api_token');
            $deviceName = $request->input('device_name') ?? 'Unknown Device'; // Default to 'Unknown Device' if not provided

            // Check if the api_token was provided
            if (!$apiToken) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Authorization token not provided',
                ], 200); // Bad Request
            }

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
                    'message' => 'User not found',
                ], 200); // Unauthorized
            }

            // Fetch user settings
            $settings = Setting::where('user_id', $user->id)->first();

            // Initialize response data arrays
            $itemsData = [];
            $partiesData = [];
            $touchData = []; // Initialized as empty
            $notesData = []; // Initialized as empty

            // Fetch items
            $items = Item::where('created_by', $user->id)
                ->select('id', 'item_name')
                ->get();

            $itemsData = $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                ];
            });

            // Fetch parties
            $parties = Party::where('created_by', $user->id)
                ->select('id', 'party_name')
                ->distinct() // Ensure unique party names are returned
                ->get();

            $partiesData = $parties->map(function ($party) {
                return [
                    'id' => $party->id,
                    'party_name' => $party->party_name,
                ];
            });

            // Handle touch data based on settings
            // if ($settings) { // Check if settings exist
                // if ($settings->touch == 1) {
                    $touchData = Transaction::where('user_id', $user->id)
                        ->select('id', 'touch')
                        ->distinct()
                        ->get()
                        ->filter(function ($transaction) {
                            return !is_null($transaction->touch); // Filter out null touch values
                        })
                        ->unique('touch') // Remove duplicates based on touch value
                        ->map(function ($transaction) {
                            return [
                                'id' => $transaction->id,
                                'touch' => $transaction->touch, // Adjust as necessary
                            ];
                        })
                        ->toArray(); // Convert collection to indexed array
                // }

                // Handle notes data based on settings
                // if ($settings->notes == 1) {
                    $notesData = Transaction::where('user_id', $user->id)
                        ->select('id', 'note')
                        ->distinct()
                        ->get()
                        ->filter(function ($note) {
                            return !is_null($note->note); // Filter out null note values
                        })
                        ->unique('note') // Remove duplicates based on note value
                        ->map(function ($note) {
                            return [
                                'id' => $note->id,
                                'note' => $note->note, // Adjust as necessary
                            ];
                        })
                        ->toArray(); // Convert collection to indexed array
                // }
            // } else {
            //     // If settings do not exist, return empty arrays for touch and notes
            //     $touchData = [];
            //     $notesData = [];
            // }

            // Prepare settings data
            $settingsData = $settings ? [$settings->toArray()] : []; // Return empty array if settings not found

            // Return the combined data in the response
            return response()->json([
                'status' => 1,
                'message' => 'Success',
                'data' => [
                    'items' => $itemsData,
                    'parties' => $partiesData,
                    'touch' => array_values($touchData), // Separate touch data as indexed array
                    'notes' => array_values($notesData), // Separate notes data as indexed array
                    'settings' => $settingsData, // Use the prepared settings data
                ],
            ], 200);
        } catch (\Exception $e) {
            // Log the error message and stack trace
            Log::channel('custom_errorlog')->error('Error in fetchData: ' . $e->getMessage(), ['exception' => $e]);

            // Return an error response
            return response()->json([
                'status' => 0,
                'message' => 'An error occurred while processing the request',
            ], 200); // Internal Server Error
        }
    }

    public function checkStatus(Request $request)
    {
        // Retrieve the api_token, transaction_id, and the is_checked status from the request
        $apiToken = $request->input('api_token');
        $deviceName = $request->input('device_name') ?? 'Unknown Device'; // Default to 'Unknown Device' if not provided
        $transactionId = $request->input('transaction_id');
        $isChecked = $request->input('is_checked'); // This should be 1 for checked, 0 for unchecked

        // Check if the api_token was provided
        if (!$apiToken) {
            return response()->json([
                'status' => 0,
                'message' => 'Authorization token not provided',
            ], 200); // Bad Request
        }

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

        // Validate that the transaction ID is provided
        if (!$transactionId) {
            return response()->json([
                'status' => 0,
                'message' => 'Transaction ID not provided',
            ], 200); // Bad Request
        }

        // Find the transaction by the provided transaction ID and ensure it belongs to the user
        $transaction = Transaction::where('id', $transactionId)
            ->where('user_id', $user->id)
            ->first();

        // Check if the transaction was found
        if (!$transaction) {
            return response()->json([
                'status' => 0,
                'message' => 'Transaction not found',
            ], 200); // Not Found
        }

        // Validate the is_checked input
        // if (!in_array($isChecked, [0, 1])) {
        //     return response()->json([
        //         'status' => 0,
        //         'message' => 'Invalid value for is_checked. Must be 0 or 1',
        //     ], 200); // Bad Request
        // }

        // Check if is_checked is null, if so set it to 0
        if ($isChecked === null) {
            $isChecked = 0; // Default to 0 if null is passed
        }

        // Update the is_checked field for the transaction
        $transaction->is_checked = $isChecked;
        $transaction->save();

        // Prepare a success message
        $message = $isChecked ? 'Transaction marked as checked' : 'Transaction marked as unchecked';

        // Return a success response
        return response()->json([
            'status' => 1,
            'message' => $message,
            'data' => [
                'transaction_id' => $transaction->id,
                'is_checked' => $transaction->is_checked,
            ],
        ], 200);
    }

    // public function checkStatus(Request $request)
    // {
    //     // Retrieve the api_token and device_name from the request parameters
    //     $apiToken = $request->input('api_token');
    //     $deviceName = $request->input('device_name') ?? 'Unknown Device';

    //     // Check if the api_token was provided
    //     if (!$apiToken) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Authorization token not provided',
    //         ], 200); // Bad Request
    //     }

    //     // Find the user associated with the api_token and device_name in the user_tokens table
    //     $tokenEntry = DB::table('user_tokens')
    //     ->whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])
    //         ->where('device_name', $deviceName)
    //         ->first();

    //     // Check if the token entry was found
    //     if (!$tokenEntry) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Token is invalid for this device',
    //         ], 200); // Unauthorized
    //     }

    //     // Fetch the user associated with the token entry
    //     $user = userslogin::find($tokenEntry->user_id);

    //     if (!$user) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Token is invalid',
    //         ], 200); // Unauthorized
    //     }

    //     // Retrieve transaction_id from the request
    //     $transactionId = $request->input('transaction_id');

    //     // Check if transaction_id was provided
    //     if (!$transactionId) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Transaction ID not provided',
    //         ], 200); // Bad Request
    //     }

    //     // Find the transaction by ID and ensure it belongs to the user
    //     $transaction = Transaction::where('id', $transactionId)
    //         ->where('user_id', $user->id)
    //         ->first();

    //     // Check if the transaction exists
    //     if (!$transaction) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Transaction not found',
    //         ], 200); // Not Found
    //     }

    //     // Toggle the is_checked field (0 to 1 or 1 to 0)
    //     $transaction->is_checked = !$transaction->is_checked;
    //     $transaction->save(); // Save the updated status

    //     // Return the updated status in the response
    //     return response()->json([
    //         'status' => 1,
    //         'message' => 'Check status updated successfully',
    //         'data' => [
    //             'transaction_id' => $transaction->id,
    //             'is_checked' => $transaction->is_checked, // Return the updated status
    //         ],
    //     ], 200); // Success
    // }

    // public function commonData(Request $request)
    // {
    //     try {
    //         // Log the request data
    //         // TransactionsLog::create([
    //         //     'request_data' => json_encode($request->all()),
    //         // ]);

    //         // Retrieve the api_token from the request parameters
    //         $apiToken = $request->input('api_token');

    //         // Check if the api_token was provided
    //         if (!$apiToken) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Authorization token not provided',
    //             ], 200); // Bad Request
    //         }

    //         // Find the user associated with the api_token
    //         $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //         if (!$user) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Token is invalid',
    //             ], 200); // Unauthorized
    //         }

    //         // Fetch user settings
    //         $settings = Setting::where('user_id', $user->id)->first();

    //         // Initialize response data arrays
    //         $itemsData = [];
    //         $partiesData = [];
    //         $touchData = []; // Initialized as empty
    //         $notesData = []; // Initialized as empty

    //         // Fetch items
    //         $items = Item::where('created_by', $user->id)
    //             ->select('id', 'item_name')
    //             ->get();

    //         $itemsData = $items->map(function ($item) {
    //             return [
    //                 'id' => $item->id,
    //                 'item_name' => $item->item_name,
    //             ];
    //         });

    //         // Fetch parties
    //         $parties = Party::where('created_by', $user->id)
    //             ->select('id', 'party_name')
    //             ->distinct() // Ensure unique party names are returned
    //             ->get();

    //         $partiesData = $parties->map(function ($party) {
    //             return [
    //                 'id' => $party->id,
    //                 'party_name' => $party->party_name,
    //             ];
    //         });

    //         // Handle touch data based on settings
    //         if ($settings) { // Check if settings exist
    //             if ($settings->touch == 1) {
    //                 $touchData = Transaction::where('user_id', $user->id)
    //                     ->select('id', 'touch')
    //                     ->distinct()
    //                     ->get()
    //                     ->filter(function ($transaction) {
    //                         return !is_null($transaction->touch); // Filter out null touch values
    //                     })
    //                     ->unique('touch') // Remove duplicates based on touch value
    //                     ->map(function ($transaction) {
    //                         return [
    //                             'id' => $transaction->id,
    //                             'touch' => $transaction->touch, // Adjust as necessary
    //                         ];
    //                     })
    //                     ->toArray(); // Convert collection to indexed array
    //             }

    //             // Handle notes data based on settings
    //             if ($settings->notes == 1) {
    //                 $notesData = Transaction::where('user_id', $user->id)
    //                     ->select('id', 'note')
    //                     ->distinct()
    //                     ->get()
    //                     ->filter(function ($note) {
    //                         return !is_null($note->note); // Filter out null note values
    //                     })
    //                     ->unique('note') // Remove duplicates based on note value
    //                     ->map(function ($note) {
    //                         return [
    //                             'id' => $note->id,
    //                             'note' => $note->note, // Adjust as necessary
    //                         ];
    //                     })
    //                     ->toArray(); // Convert collection to indexed array
    //             }
    //         } else {
    //             // If settings do not exist, return empty arrays for touch and notes
    //             $touchData = [];
    //             $notesData = [];
    //         }

    //         // Prepare settings data
    //         $settingsData = $settings ? [$settings->toArray()] : []; // Return empty array if settings not found

    //         // Return the combined data in the response
    //         return response()->json([
    //             'status' => 1,
    //             'message' => 'Success',
    //             'data' => [
    //                 'items' => $itemsData,
    //                 'parties' => $partiesData,
    //                 'touch' => array_values($touchData), // Separate touch data as indexed array
    //                 'notes' => array_values($notesData), // Separate notes data as indexed array
    //                 'settings' => $settingsData, // Use the prepared settings data
    //             ],
    //         ], 200);
    //     } catch (\Exception $e) {
    //         // Log the error message and stack trace
    //         Log::channel('custom_errorlog')->error('Error in fetchData: ' . $e->getMessage(), ['exception' => $e]);

    //         // Return an error response
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'An error occurred while processing the request',
    //         ], 200); // Internal Server Error
    //     }
    // }

    // public function commonData1(Request $request)
    // {
    //     try {
    //         // Log the request data
    //         // TransactionsLog::create([
    //         //     'request_data' => json_encode($request->all()),
    //         // ]);

    //         // Retrieve the api_token from the request parameters
    //         $apiToken = $request->input('api_token');

    //         // Check if the api_token was provided
    //         if (!$apiToken) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Authorization token not provided',
    //             ], 200); // Bad Request
    //         }

    //         // Find the user associated with the api_token
    //         $user = userslogin::whereRaw('LOWER(api_token) = ?', [strtolower($apiToken)])->first();

    //         if (!$user) {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Token is invalid',
    //             ], 200); // Unauthorized
    //         }

    //         // Fetch user settings
    //         $settings = Setting::where('user_id', $user->id)->first();
    //         // Prepare settings data
    //         // $settingsData = $settings ? [$settings->toArray()] : []; // Return empty array if settings not found

    //         // if (!$settings) {
    //         //     return response()->json([
    //         //         'status' => 0,
    //         //         'message' => 'User settings not found',
    //         //     ], 200); // No settings found for user
    //         // }

    //         // Initialize response data arrays
    //         $itemsData = [];
    //         $partiesData = [];
    //         // $transactionsData = collect(); // Start with an empty collection for transactions
    //         // $touchData = collect();  // Collection to store touch data
    //         // $notesData = collect();  // Collection to store note data
    //         $touchData = [];
    //         $notesData = [];

    //         $items = Item::where('created_by', $user->id)
    //             ->select('id', 'item_name')
    //             ->get();

    //         $itemsData = $items->map(function ($item) {
    //             return [
    //                 'id' => $item->id,
    //                 'item_name' => $item->item_name,
    //             ];
    //         });


    //         $parties = Party::where('created_by', $user->id)
    //             // ->join('parties', 'transactions.party_id', '=', 'parties.id')
    //             ->select('id', 'party_name')
    //             ->distinct() // Ensure unique party names are returned
    //             ->get();

    //         $partiesData = $parties->map(function ($party) {
    //             return [
    //                 'id' => $party->id,
    //                 'party_name' => $party->party_name,
    //             ];
    //         });

    //         // Fetch transactions if touch is enabled (1)
    //         if ($settings->touch == 1) {
    //             $touchData = Transaction::where('user_id', $user->id)
    //                 ->select('id', 'touch')
    //                 ->distinct()
    //                 ->get()
    //                 ->filter(function ($transaction) {
    //                     return !is_null($transaction->touch); // Filter out null touch values
    //                 })
    //                 ->unique('touch') // Remove duplicates based on touch value
    //                 ->map(function ($transaction) {
    //                     return [
    //                         'id' => $transaction->id,
    //                         'touch' => $transaction->touch, // Adjust as necessary
    //                     ];
    //                 })->toArray();

    //             // Add touch data to transactionsData collection
    //             // $transactionsData = $transactionsData->merge($touchData);
    //             // $touchData = $touchData->toArray(); // Convert collection to indexed array
    //         } else {
    //             $touchData = []; // Set to empty array if touch setting is not enabled
    //         }

    //         // If notes are enabled (1), you can add logic here to fetch notes
    //         if ($settings->notes == 1) {
    //             // Assuming there's a note column in the transactions table
    //             $notesData = Transaction::where('user_id', $user->id)
    //                 ->select('id', 'note')
    //                 ->distinct()
    //                 ->get()
    //                 ->filter(function ($note) {
    //                     return !is_null($note->note); // Filter out null note values
    //                 })
    //                 ->unique('note') // Remove duplicates based on note value
    //                 ->map(function ($note) {
    //                     return [
    //                         'id' => $note->id,
    //                         'note' => $note->note, // Adjust as necessary
    //                     ];
    //                 })->toArray();

    //             // Merge notes into transactionsData if needed
    //             // $transactionsData = array_merge($transactionsData->toArray(), $notesData->toArray());

    //             // Add touch data to transactionsData collection
    //             // $transactionsData = $transactionsData->merge($notesData);

    //             // $notesData = $notesData->toArray(); // Convert collection to indexed array

    //         } else {
    //             $notesData = []; // Set to empty array if notes setting is not enabled
    //         }

    //         // Return the combined data in the response
    //         return response()->json([
    //             'status' => 1,
    //             'message' => 'Success',
    //             'data' => [
    //                 'items' => $itemsData,
    //                 'parties' => $partiesData,
    //                 'touch' => array_values($touchData), // Separate touch data as indexed array
    //                 'notes' => array_values($notesData), // Separate notes data as indexed array
    //                 // 'settings' => $settingsData,
    //                 // 'settings' => [$settings->toArray()],
    //                 // 'transactions' => $transactionsData->toArray(),
    //                 // 'touch' => $touchData->toArray(),   // Send touch data separately
    //                 // 'notes' => $notesData->toArray(),   // Send note data separately
    //                 // 'settings' => $settings,
    //             ],
    //         ], 200);
    //     } catch (\Exception $e) {
    //         // Log the error message and stack trace
    //         Log::channel('custom_errorlog')->error('Error in fetchData: ' . $e->getMessage(), ['exception' => $e]);

    //         // Return an error response
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'An error occurred while processing the request',
    //         ], 200); // Internal Server Error
    //     }
    // }
}
