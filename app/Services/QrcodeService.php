<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Exception;

class QrcodeService
{
    public function createFolder(): string
    {
        $year = date('Y'); // Get the current year
        $folderName = "{$year}_qrcode";
        $folderPath = storage_path("app/invoices/{$folderName}");

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

        $svgFileName = "qrcode_{$id}.png";
        $svgFilePath = "{$folderPath}/{$svgFileName}";

        // Save the QR code content as SVG
        try {
            file_put_contents($svgFilePath, $qrcode);
        } catch (Exception $e) {
            throw new Exception("Failed to save QR code SVG: {$e->getMessage()}");
        }

        return [
            'png' => $svgFilePath,
        ];
    }

    public function generateQrCode($id, $text): string
    {
        $folderPath = $this->createFolder();

        // Generate the QR code directly from the provided text (e.g., EINV_QR Base64 data) in SVG format
        $qrcode = QrCode::format('png')
            ->size(500)
            ->generate('AQACAnt9AwVmYWxzZQQNODEwLjUwMDAwMDAwMAUFMzIwMzAGCzMuNTAwMDAwMDAwBwoyMDI1LTAyLTA2CAcxNTIyODUwCRjZh9in2YrZhCDYp9io2Ygg2LXZitin2YUKYE1FVUNJUURoSXhLTDhvN2FjUGhUOGFzR1dTcnVjTnFkc2IzRWoyUkRiZ3FKNDFFZlRnSWdIZ3dWWkxpU2FpdGd3Wm8zOEtvOGhSZHRJZjBXcUh6NzE5N3VORnlOWG1vPQ==');

        $filePaths = $this->saveQrcode($qrcode, $folderPath, $id);

        return $filePaths['png']; // Return SVG path
    }
}
