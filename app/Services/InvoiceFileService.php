<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class InvoiceFileService
{
    // Create a folder based on the invoice type if it doesn't exist inside app/storage
    public function createFolder($invoiceType)
    {
        // Determine the folder name based on the invoice type
        $folderName = strtolower($invoiceType === '388' ? 'general_sales_invoice' : 'credit_sales_invoice');

        // Construct the full path to the folder (inside storage/app/invoices)
        $folderPath = storage_path('app/invoices/' . $folderName);

        // Check if the folder exists, if not, create it
        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);  // Create the directory with the right permissions
        }

        return $folderPath;
    }

    // Save the XML file to the respective folder inside app/storage
    public function saveInvoiceXml($xml, $folderPath, $id, $invoiceType)
    {
        // Generate the file name based on invoice type and ID
        $fileName = $invoiceType === '388' ? 'general_sales_invoice_' : 'credit_sales_invoice_';
        $fileName .= $id . '.xml';

        // Combine the folder path and file name to get the full file path inside app/storage
        $filePath = $folderPath . '/' . $fileName;

        // Save the XML content to the file
        file_put_contents($filePath, $xml);

        return $filePath;
    }
}
