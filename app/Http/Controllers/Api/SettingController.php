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
        return response()->json(Setting::current());
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'semi_wholesale_percentage' => 'required|numeric|min:0|max:100',
            'retail_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $setting = Setting::current();
        $setting->update($validated);

        return response()->json($setting);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $setting = Setting::current();

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($setting->company_logo) {
                Storage::disk('public')->delete($setting->company_logo);
            }

            $path = $request->file('logo')->store('logos', 'public');
            $setting->update(['company_logo' => $path]);
        }

        return response()->json(['company_logo' => $setting->company_logo]);
    }
}
