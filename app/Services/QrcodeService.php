<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Exception;

class QrcodeService
{
    protected $basePath = 'D:\qr_codes'; // Define the base path for QR code storage

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

        $pngFileName = "qrcode_{$id}.png";
        $pngFilePath = "{$folderPath}/{$pngFileName}";

        // Save the QR code content as PNG
        try {
            file_put_contents($pngFilePath, $qrcode);
        } catch (Exception $e) {
            throw new Exception("Failed to save QR code PNG: {$e->getMessage()}");
        }

        return [
            'png' => $pngFilePath,
        ];
    }

    public function generateQrCode($id, $text): string
    {
        $folderPath = $this->createFolder();

        // Generate the QR code directly from the provided text (e.g., EINV_QR Base64 data) in PNG format
        $qrcode = QrCode::format('png')
            ->size(500)
            ->generate($text);

        $filePaths = $this->saveQrcode($qrcode, $folderPath, $id);

        return $filePaths['png']; // Return PNG path
    }
}
