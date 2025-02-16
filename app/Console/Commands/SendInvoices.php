<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceService;
use App\Services\InvoiceFileService;
use App\Services\LicenseService; // Add the LicenseService for validation

class SendInvoices extends Command
{
    protected $signature = 'send:invoices';
    protected $description = 'Send invoices to the external API.';

    protected $invoiceService;
    protected $invoiceFileService;
    protected $licenseService; // Add license service

    public function __construct(InvoiceService $invoiceService, InvoiceFileService $invoiceFileService, LicenseService $licenseService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
        $this->invoiceFileService = $invoiceFileService;
        $this->licenseService = $licenseService; // Initialize license service
    }

    public function handle()
    {
        // ✅ Step 1: Check if the license is valid
        if (!$this->licenseService->isValid()) {
            $this->error('Application is locked. Please activate your license.');
            return Command::FAILURE; // Exit early if license is invalid
        }

        // ✅ Step 2: Fetch all invoices that need to be sent
        $invoices = DB::connection('mysql')->table('fawtara_01')->where('sent_to_fawtara', 0)->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices to send.');
            return Command::SUCCESS;
        }

        foreach ($invoices as $invoice) {
            try {
                // Fetch related items
                $items = DB::connection("mysql")->table("fawtara_02")->where("uuid", $invoice->uuid)->where('invoice_type', $invoice->invoice_type)->get();
                $invoice->items = $items;

                // Prepare the XML based on invoice type
                if ($invoice->invoice_type === '388') { // General Sales Invoice
                    $xmlData = $this->invoiceService->generateGeneralSalesInvoiceXml($invoice);
                } elseif ($invoice->invoice_type === '381') { // Credit Invoice
                    $xmlData = $this->invoiceService->generateCreditInvoiceXml($invoice);
                } else {
                    $this->error('Failed to send invoice ID: ' . $invoice->uuid . '. Unsupported invoice type.');
                    continue;
                }

                // Create a folder and save XML file
                $folderPath = $this->invoiceFileService->createFolder($invoice->invoice_type);
                $filePath = $this->invoiceFileService->saveInvoiceXml($xmlData, $folderPath, $invoice->uuid, $invoice->invoice_type);

                // Send the XML to the external API
                $response = $this->invoiceService->sendInvoiceToApi($filePath, $invoice->uuid);

                // Check the API response and update database
                if ($response['status']) {
                    $this->info('Invoice ID ' . $invoice->uuid . ' has been sent successfully.');

                    DB::connection('mysql')
                        ->table('fawtara_01')
                        ->where('uuid', $invoice->uuid)
                        ->update(['sent_to_fawtara' => 1, 'qr_code' => $response['data']['EINV_QR']]);

                    DB::connection('mysql')->table('fawtara_02')->where('uuid', $invoice->uuid)->update(['sent_to_fawtara' => 1]);
                } else {
                    $this->error('Failed to send invoice ID ' . $invoice->uuid . '. Error: ' . $response['message']);
                }
            } catch (\Exception $e) {
                $this->error('Error sending invoice ID ' . $invoice->uuid . ': ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
