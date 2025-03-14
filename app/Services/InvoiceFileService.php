<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class InvoiceFileService
{
    // Create a folder based on the invoice type if it doesn't exist inside app/storage
    public function createFolder($invoiceType)
    {
        // Get the current year
        $currentYear = date('Y');

        // Define the parent folder path (year-based)
        $yearFolderPath = storage_path('app/invoices/' . $currentYear);

        // Check if the year folder exists, if not, create it
        if (!File::exists($yearFolderPath)) {
            File::makeDirectory($yearFolderPath, 0755, true);  // Create the directory with the right permissions
        }

        // Return the path to the year folder
        return $yearFolderPath;
    }

    // Save the XML file to the respective folder inside app/storage
    public function saveInvoiceXml($xml, $yearFolderPath, $id, $invoiceType, $invoicetypecode)
    {
        // Define base folder based on invoice type
        switch ($invoiceType) {
            case '388':
                $baseFolder = 'general_sales'; // General Sales Invoices
                $filePrefix = 'general_sale_invoice';
                break;
            case '381':
                $baseFolder = 'return_invoices'; // Return Invoices
                $filePrefix = 'return_invoice';
                break;
            default:
                return false; // Invalid invoice type
        }

        // Define subfolder based on subtype
        $subFolder = ($invoicetypecode === '022') ? 'cash_sales' : 'credit_sales';

        // Construct the full folder path
        $folderPath = "{$yearFolderPath}/{$baseFolder}/{$subFolder}";

        // Ensure the folder exists
        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);
        }

        // Generate the file name (without type/subtype codes)
        $fileName = "{$filePrefix}_{$id}.xml";

        // Save the XML content
        file_put_contents("{$folderPath}/{$fileName}", $xml);

        return "{$folderPath}/{$fileName}";
    }
}
