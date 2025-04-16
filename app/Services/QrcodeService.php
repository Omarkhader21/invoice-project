<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Exception;

class QrcodeService
{
    protected $basePath; // Define the base path for QR code storage

    public function __construct()
    {
        $this->basePath = storage_path('app/invoices'); // Store in storage/app/invoices
    }

    public function createFolder(): string
    {
        $year = date('Y'); // Get the current year
        $folderName = "{$year}_qrcode";
        $folderPath = "{$this->basePath}/{$folderName}";

        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);
        }

        return $folderPath;
    }

    public function saveQrcode($qrcode, $folderPath, $id): array
    {
        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);
        }

        $svgFileName = "qrcode_{$id}.svg";
        $svgFilePath = "{$folderPath}/{$svgFileName}";

        // Save the QR code content as SVG
        try {
            file_put_contents($svgFilePath, $qrcode);
        } catch (Exception $e) {
            throw new Exception("Failed to save QR code SVG: {$e->getMessage()}");
        }

        return [
            'svg' => $svgFilePath,
        ];
    }

    public function generateQrCode($id, $text): string
    {
        $folderPath = $this->createFolder();

        // Generate the QR code directly from the provided text (e.g., EINV_QR Base64 data) in SVG format
        $qrcode = QrCode::format('svg')
            ->size(500)
            ->generate($text);

        $filePaths = $this->saveQrcode($qrcode, $folderPath, $id);

        return $filePaths['svg']; // Return SVG path
    }
}
