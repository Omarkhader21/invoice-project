<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class InvoiceFileService
{
    // Create a folder based on the invoice mode and type inside app/storage
    public function createFolder($invoiceType)
    {
        // Get the current year
        $currentYear = date('Y');

        // Get the invoice mode from configuration
        $invoiceMode = config('app.invoice_type');

        // Validate invoice mode
        if (!in_array($invoiceMode, ['sales', 'income'])) {
            return false; // Invalid invoice mode
        }

        // Define the parent folder path (year and mode-based)
        $baseFolderPath = storage_path("app/invoices/{$currentYear}/{$invoiceMode}");

        // Check if the base folder exists, if not, create it
        if (!File::exists($baseFolderPath)) {
            File::makeDirectory($baseFolderPath, 0755, true); // Create the directory with the right permissions
        }

        // Return the path to the mode folder
        return $baseFolderPath;
    }

    // Save the XML file to the respective folder inside app/storage
    public function saveInvoiceXml($xml, $baseFolderPath, $id, $invoiceType, $invoicetypecode)
    {
        // Define base folder and file prefix based on config('app.invoice_type') and invoice type
        $invoiceMode = config('app.invoice_type');

        switch ($invoiceMode) {
            case 'sales':
                if ($invoiceType === '388') {
                    $typeFolder = 'general_sales'; // General Sales Invoices
                    $filePrefix = 'general_sale_invoice';
                } elseif ($invoiceType === '381') {
                    $typeFolder = 'return_sales_invoices'; // Return Sales Invoices
                    $filePrefix = 'return_sales_invoice';
                } else {
                    return false; // Invalid invoice type for sales mode
                }
                break;
            case 'income':
                if ($invoiceType === '388') {
                    $typeFolder = 'general_income'; // General Income Invoices
                    $filePrefix = 'general_income_invoice';
                } elseif ($invoiceType === '381') {
                    $typeFolder = 'return_income_invoices'; // Return Income Invoices
                    $filePrefix = 'return_income_invoice';
                } else {
                    return false; // Invalid invoice type for income mode
                }
                break;
            default:
                return false; // Invalid invoice mode
        }

        // Define subfolder based on subtype
        $subFolder = ($invoicetypecode === '022') ? 'cash_sales' : 'credit_sales';

        // Construct the full folder path
        $folderPath = "{$baseFolderPath}/{$typeFolder}/{$subFolder}";

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
