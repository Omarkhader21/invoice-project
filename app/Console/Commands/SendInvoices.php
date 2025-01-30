<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceService; // A service to handle invoice logic
use App\Services\InvoiceFileService; // Add the InvoiceFileService for managing files

class SendInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send invoices to the external API.';

    /**
     * The Invoice Service instance.
     *
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * The Invoice File Service instance.
     *
     * @var InvoiceFileService
     */
    protected $invoiceFileService;

    /**
     * Create a new command instance.
     *
     * @param InvoiceService $invoiceService
     * @param InvoiceFileService $invoiceFileService
     */
    public function __construct(InvoiceService $invoiceService, InvoiceFileService $invoiceFileService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService; // This will resolve to InvoiceXmlService if injected
        $this->invoiceFileService = $invoiceFileService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Fetch all invoices that need to be sent
        $invoices = DB::connection(name: 'mysql')->table('fawtara-01')->where('sent-to-fawtara', 0)->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices to send.');
            return;
        }

        foreach ($invoices as $invoice) {
            try {
                // Fetch related items
                $items = DB::connection("mysql")->table("fawtara-02")->where("uuid", $invoice->uuid)->where('invoice-type', $invoice->{'invoice-type'})->get();
                $invoice->items = $items;

                // Prepare the XML for this invoice
                if ($invoice->{'invoice-type'} === '388') { // General Sales Invoice
                    $xmlData = $this->invoiceService->generateGeneralSalesInvoiceXml($invoice);
                } elseif ($invoice->{'invoice-type'} === '381') { // Credit Invoice
                    $xmlData = $this->invoiceService->generateCreditInvoiceXml($invoice);
                } else {
                    $this->error('Failed to send invoice ID: ' . $invoice->uuid . '. Unsupported invoice type.');
                    continue;
                }

                // Create the folder for the invoice type
                $folderPath = $this->invoiceFileService->createFolder($invoice->{'invoice-type'});

                // Save the XML to a file using the InvoiceFileService
                $filePath = $this->invoiceFileService->saveInvoiceXml($xmlData, $folderPath, $invoice->uuid, $invoice->{'invoice-type'}); // Use the method from InvoiceFileService to save the file

                // Send the XML to the external API endpoint using the service method
                $response = $this->invoiceService->sendInvoiceToApi($filePath, $invoice->uuid);

                // Check the response status and update the invoice accordingly
                if ($response['status']) {
                    // If the API call was successful, mark the invoice as 'sent'
                    $invoice->update(['sent-to-fawtara' => 1, 'status' => 'sent']);
                    $this->info('Invoice ID ' . $invoice->uuid . ' has been sent successfully.');

                    // Update the 'sent-to-fawtara' field in the database
                    DB::connection('mysql')
                        ->table('fawtara-01')
                        ->where('uuid', $id)
                        ->update(['sent-to-fawtara' => 1]);
                } else {
                    // If the API call failed, log the error message
                    $this->error('Failed to send invoice ID ' . $invoice->uuid . '. Error: ' . $response['message']);
                }
            } catch (\Exception $e) {
                // Catch any exceptions and log the error
                $this->error('Error sending invoice ID ' . $invoice->uuid . ': ' . $e->getMessage());
            }
        }
    }
}
