<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LicenseService
{
    protected $licenseFile;

    public function __construct()
    {
        $this->licenseFile = storage_path('license.json');
    }

    // Generate a license file on first run
    public function initialize()
    {
        if (!File::exists($this->licenseFile)) {
            $data = [
                'machine_id'  => $this->getMachineID(),
                'license_key' => Str::uuid(), // Generate unique key
                'installed_at' => now()->toDateString(),
                'expires_at'   => now()->addYear()->toDateString(),
                'is_active'    => false
            ];
            File::put($this->licenseFile, json_encode($data, JSON_PRETTY_PRINT));

            return "License file created successfully.";
        }

        return "License file already exists.";
    }

    // Check if the license is still valid
    public function isValid()
    {
        if (!File::exists($this->licenseFile)) {
            return false;
        }

        $data = json_decode(File::get($this->licenseFile), true);
        if (!$data) {
            return false; // Corrupt or invalid JSON
        }

        return $data['is_active'] && Carbon::parse($data['expires_at'])->greaterThanOrEqualTo(now());
    }

    // Activate the software with a valid key
    public function activate($licenseKey)
    {
        if (!File::exists($this->licenseFile)) {
            return false;
        }

        $data = json_decode(File::get($this->licenseFile), true);
        if (!$data || !isset($data['license_key'])) {
            return false; // Corrupt file or missing license key
        }

        if ($data['license_key'] !== $licenseKey) {
            return false; // Invalid key
        }

        $data['is_active'] = true;
        $data['expires_at'] = now()->addYear()->toDateString(); // Extend 1 Year

        File::put($this->licenseFile, json_encode($data, JSON_PRETTY_PRINT));

        return true;
    }

    // Get unique Machine ID (to prevent copying)
    public function getMachineID()
    {
        // For Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return  trim(shell_exec('powershell -Command "(Get-WmiObject Win32_NetworkAdapter | Where-Object { $_.MACAddress -ne $null }).MACAddress"'));
        }

        // For Linux
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'LIN') {
            return trim(shell_exec('cat /var/lib/dbus/machine-id'));
        }

        // For Mac
        if (strtoupper(substr(PHP_OS, 0, 6)) === 'DARWIN') {
            return trim(shell_exec('ioreg -rd1 -c IOPlatformExpertDevice | grep -E "UUID" | sed "s/.*<//;s/>.*//"'));
        }

        return null; // If OS is not recognized
    }
}
