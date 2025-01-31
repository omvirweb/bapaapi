<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\userslogin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function settings(Request $request)
    {

        try {

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

            // Validate the incoming request data
            $request->validate([
                'touch' => 'required|boolean',
                'notes' => 'required|boolean',
            ]);

            // Check if the settings for this user already exist
            $setting = Setting::where('user_id', $user->id)->first();

            if ($setting) {
                // Update existing settings
                $setting->update([
                    'touch' => $request->input('touch', 0),
                    'notes' => $request->input('notes', 0),
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Settings updated successfully',
                    'data' => $setting,
                ]);
            } else {
                // Create a new settings record
                $setting = Setting::create([
                    'user_id' => $user->id,
                    'touch' => $request->input('touch', 0),
                    'notes' => $request->input('notes', 0),
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Settings created successfully',
                    'data' => $setting,
                ]);
            }
        } catch (\Exception $e) {
            // Log the error message and stack trace
            Log::channel('custom_errorlog')->error('Error in settings: ' . $e->getMessage(), ['exception' => $e]);

            // Return a generic error response
            return response()->json([
                'status' => 0,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage(), // You can remove this in production if you don't want to expose error details
            ], 500); // Internal Server Error
        }
    }
}
