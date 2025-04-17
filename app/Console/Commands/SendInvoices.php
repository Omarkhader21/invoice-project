<?php

namespace App\Console\Commands;

use App\Services\QrcodeService;
use Illuminate\Console\Command;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceFileService;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Log;

class SendInvoices extends Command
{
    protected $signature = 'send:invoices';
    protected $description = 'Send invoices to the external API.';
    protected $invoiceService;
    protected $invoiceFileService;
    protected $licenseService;
    protected $qrcodeService;

    public function __construct(
        InvoiceService $invoiceService,
        InvoiceFileService $invoiceFileService,
        LicenseService $licenseService,
        QrcodeService $qrcodeService
    ) {
        parent::__construct();
        $this->invoiceService = $invoiceService;
        $this->invoiceFileService = $invoiceFileService;
        $this->licenseService = $licenseService;
        $this->qrcodeService = $qrcodeService;
    }

    public function handle()
    {
        // Step 1: Check if the license is valid
        if (!$this->licenseService->isValid()) {
            $this->error('Application is locked. Please activate your license.');
            Log::error('Invoice sending aborted: Invalid license.');
            return Command::FAILURE;
        }

        // Step 2: Fetch all invoices that need to be sent
        $invoices = DB::connection('mysql')
            ->table('fawtara_01')
            ->where('sent_to_fawtara', 0)
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices to send.');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($invoices as $invoice) {
            try {
                // Fetch related items
                $items = DB::connection('mysql')
                    ->table('fawtara_02')
                    ->where('uuid', $invoice->uuid)
                    ->where('invoice_type', $invoice->invoice_type)
                    ->get();
                $invoice->items = $items;

                // Prepare the XML based on invoice type
                if ($invoice->invoice_type === '388') {
                    $xmlData = $this->invoiceService->generateGeneralSalesInvoiceXml($invoice);
                } elseif ($invoice->invoice_type === '381') {
                    $xmlData = $this->invoiceService->generateCreditInvoiceXml($invoice);
                } else {
                    $errorMessage = 'Unsupported invoice type: ' . $invoice->invoice_type;
                    $this->error('Failed to send invoice ID: ' . $invoice->uuid . '. ' . $errorMessage);
                    Log::error('Invoice ID: ' . $invoice->uuid . ' failed: ' . $errorMessage);
                    $failureCount++;
                    continue;
                }

                // Create a folder and save XML file
                $folderPath = $this->invoiceFileService->createFolder($invoice->invoice_type);
                $filePath = $this->invoiceFileService->saveInvoiceXml(
                    $xmlData,
                    $folderPath,
                    $invoice->uuid,
                    $invoice->invoice_type,
                    $invoice->invoicetypecode
                );

                // Send the XML to the external API
                $response = $this->invoiceService->sendInvoiceToApi($filePath, $invoice->uuid);

                // Check the API response
                if ($response['status']) {
                    $this->info('Invoice ID ' . $invoice->uuid . ' sent successfully.');
                    Log::info('Invoice ID: ' . $invoice->uuid . ' sent successfully.', [
                        'response' => $response['data']
                    ]);

                    // Update database with success status
                    DB::connection('mysql')
                        ->table('fawtara_01')
                        ->where('uuid', $invoice->uuid)
                        ->update([
                            'sent_to_fawtara' => 1,
                            'qr_code' => $response['data']['EINV_QR'] ?? null,
                        ]);

                    DB::connection('mysql')
                        ->table('fawtara_02')
                        ->where('uuid', $invoice->uuid)
                        ->update([
                            'sent_to_fawtara' => 1,
                        ]);

                    // Uncomment if QR code generation is needed
                    // $this->qrcodeService->generateQrCode($invoice->uuid, $response['data']['EINV_QR']);
                    $successCount++;
                } else {
                    // Handle API failure
                    $errorMessage = $response['message'] ?? 'Unknown API error';
                    if (isset($response['details'])) {
                        $errorMessage .= ' Details: ' . json_encode($response['details']);
                    }
                    if (isset($response['error_code'])) {
                        $errorMessage .= ' Error Code: ' . $response['error_code'];
                    }
                    if (isset($response['http_status'])) {
                        $errorMessage .= ' HTTP Status: ' . $response['http_status'];
                    }

                    $this->error('Failed to send invoice ID: ' . $invoice->uuid . '. Error: ' . $errorMessage);
                    Log::error('Invoice ID: ' . $invoice->uuid . ' failed to send.', [
                        'error' => $errorMessage,
                        'response' => $response
                    ]);

                    $failureCount++;
                }
            } catch (\Exception $e) {
                // Handle unexpected exceptions
                $errorMessage = 'Unexpected error: ' . $e->getMessage();
                $this->error('Error sending invoice ID: ' . $invoice->uuid . ': ' . $errorMessage);
                Log::error('Invoice ID: ' . $invoice->uuid . ' encountered an error.', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $failureCount++;
            }
        }

        // Summary of processing
        $this->info("Processing complete. Successfully sent: {$successCount}, Failed: {$failureCount}");
        Log::info('Invoice sending completed.', [
            'successful' => $successCount,
            'failed' => $failureCount
        ]);

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
