<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrcodeService
{
    public function createFolder(): string
    {
        $year = date('Y'); // Get the current year
        $folderName = "{$year}_qrcode"; // Folder name with year
        $folderPath = storage_path("app/invoices/{$folderName}");

        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);
        }

        return $folderPath;
    }

    public function saveQrcode($qrcode, $folderPath, $id): string
    {
        $fileName = "qrcode_{$id}.svg";
        $filePath = "{$folderPath}/{$fileName}";

        file_put_contents($filePath, $qrcode);

        return $filePath;
    }

    public function generateQrCode($id, $text): string
    {
        $folderPath = $this->createFolder();

        // Generate the QR code content and store it as an image
        $qrcode = QrCode::format('png')->size(500)->generate($text);

        return $this->saveQrcode($qrcode, $folderPath, $id);
    }
}
