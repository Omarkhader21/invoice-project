<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LicenseController extends Controller
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    public function activate(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'license_key' => 'required|string',
        ]);

        if ($this->licenseService->activate($request->license_key)) {
            return response()->json(['message' => 'Application activated successfully']);
        }

        // Log failure for debugging
        Log::error('Invalid license key attempt', ['license_key' => $request->license_key]);

        return response()->json(['message' => 'Invalid license key'], 400);
    }


    public function isValid()
    {
        // Check if the license key is stored in cache or database
        $licenseKey = Cache::get('license_key');

        // You can also validate from a file or external API
        return $licenseKey === 'YOUR_VALID_LICENSE_KEY';
    }
}
