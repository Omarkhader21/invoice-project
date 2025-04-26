<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

abstract class InvoiceXmlService
{
    protected function generateInvoiceNumber()
    {
        $basePath = storage_path('app/invoices');
        $invoiceCount = 0;

        if (!File::exists($basePath)) {
            return 1;
        }

        // Get the invoice mode from configuration
        $invoiceMode = config('app.invoice_type');

        // Define the folders to scan based on invoice mode
        $typeFolders = ($invoiceMode === 'sales')
            ? ['general_sales', 'return_sales_invoices']
            : ['general_income', 'return_income_invoices'];

        $yearFolders = File::directories($basePath);
        foreach ($yearFolders as $yearFolder) {
            $modePath = "{$yearFolder}/{$invoiceMode}";
            if (!File::exists($modePath)) {
                continue;
            }

            foreach ($typeFolders as $typeFolder) {
                $typePath = "{$modePath}/{$typeFolder}";
                if (File::exists($typePath)) {
                    $cashSalesPath = "{$typePath}/cash_sales";
                    $creditSalesPath = "{$typePath}/credit_sales";
                    if (File::exists($cashSalesPath)) {
                        $invoiceCount += count(File::glob("{$cashSalesPath}/*.xml"));
                    }
                    if (File::exists($creditSalesPath)) {
                        $invoiceCount += count(File::glob("{$creditSalesPath}/*.xml"));
                    }
                }
            }
        }

        return $invoiceCount + 1;
    }

    protected function getCompanyInfo()
    {
        return DB::connection('mysql')->table('fawtara_00')->first();
    }

    protected function sanitizeString($string)
    {
        if (!is_string($string)) {
            return $string;
        }

        // Convert to UTF-8 if not already
        $string = mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string) ?: 'UTF-8');

        // Remove control characters (except tabs, newlines, and carriage returns)
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);

        return $string;
    }
}
