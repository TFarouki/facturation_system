<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        try {
            $setting = Setting::current();
            return response()->json($setting);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_name' => 'nullable|string|max:255',
                'company_logo' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'contact_person' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:255',
                'semi_wholesale_percentage' => 'nullable|numeric|min:0|max:100',
                'retail_percentage' => 'nullable|numeric|min:0|max:100',
                'language' => 'nullable|string|in:ar,en,fr',
            ]);

            $setting = Setting::current();
            
            // Use only() to get only the fields we want to update
            $updateData = $request->only([
                'company_name',
                'phone',
                'email',
                'address',
                'contact_person',
                'contact_phone',
                'semi_wholesale_percentage',
                'retail_percentage',
                'language'
            ]);
            
            // Handle company_logo separately - only update if explicitly provided and not empty
            if ($request->has('company_logo') && !empty($request->company_logo)) {
                $updateData['company_logo'] = $request->company_logo;
            }
            
            // Filter out null and empty string values (but keep 0)
            $filteredData = [];
            foreach ($updateData as $key => $value) {
                // For percentage fields, keep if numeric (including 0) or explicitly set
                if (in_array($key, ['semi_wholesale_percentage', 'retail_percentage'])) {
                    if (is_numeric($value)) {
                        $filteredData[$key] = $value;
                    }
                } else {
                    // For other fields, remove null and empty strings
                    if ($value !== null && $value !== '') {
                        $filteredData[$key] = $value;
                    }
                }
            }
            $updateData = $filteredData;
            
            // Always ensure percentage fields have values (use existing if not provided)
            if (!isset($updateData['semi_wholesale_percentage'])) {
                $updateData['semi_wholesale_percentage'] = $setting->semi_wholesale_percentage ?? 0;
            }
            if (!isset($updateData['retail_percentage'])) {
                $updateData['retail_percentage'] = $setting->retail_percentage ?? 0;
            }
            
            // Convert percentage values to float to ensure proper type
            if (isset($updateData['semi_wholesale_percentage'])) {
                $updateData['semi_wholesale_percentage'] = (float) $updateData['semi_wholesale_percentage'];
            }
            if (isset($updateData['retail_percentage'])) {
                $updateData['retail_percentage'] = (float) $updateData['retail_percentage'];
            }
            
            // Only update if we have data to update
            if (!empty($updateData)) {
                $setting->update($updateData);
            }
            
            $setting->refresh();

            return response()->json($setting);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Settings update error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadLogo(Request $request)
    {
        try {
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $setting = Setting::current();

            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($setting->company_logo && Storage::disk('public')->exists($setting->company_logo)) {
                    Storage::disk('public')->delete($setting->company_logo);
                }

                // Store the new logo
                $path = $request->file('logo')->store('logos', 'public');
                
                if (!$path) {
                    return response()->json([
                        'message' => 'Failed to store logo file'
                    ], 500);
                }

                // Update the setting
                $setting->company_logo = $path;
                $setting->save();
                $setting->refresh();
            } else {
                return response()->json([
                    'message' => 'No file uploaded'
                ], 422);
            }

            return response()->json(['company_logo' => $setting->company_logo]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Logo upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to upload logo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
