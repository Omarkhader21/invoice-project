<?php

namespace App\Http\Controllers\Api\Invoices;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Services\InvoiceFileService;
use App\Services\QrcodeService;
use App\Services\SalesInvoiceXmlService;
use App\Services\IncomeInvoiceXmlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class SendInvoiceController extends Controller
{
    protected $salesInvoiceService;
    protected $incomeInvoiceService;
    protected $invoiceService;
    protected $invoiceFileService;
    protected $qrcodeService;

    public function __construct(
        SalesInvoiceXmlService $salesInvoiceService,
        IncomeInvoiceXmlService $incomeInvoiceService,
        InvoiceService $invoiceService,
        InvoiceFileService $invoiceFileService,
        QrcodeService $qrcodeService
    ) {
        $this->salesInvoiceService = $salesInvoiceService;
        $this->incomeInvoiceService = $incomeInvoiceService;
        $this->invoiceService = $invoiceService;
        $this->invoiceFileService = $invoiceFileService;
        $this->qrcodeService = $qrcodeService;
    }

    public function sendInvoice($id)
    {
        // Fetch invoice data
        $invoiceData = DB::connection('mysql')
            ->table('fawtara_01')
            ->where('uuid', $id)
            ->where('sent_to_fawtara', 0)
            ->get();

        if ($invoiceData->isEmpty()) {
            flash()->error("Invoice not found or already sent.");
            return redirect()->back();
        }

        [$invoiceData] = $invoiceData; // Extract the first (and only) record

        // Fetch related items
        $items = DB::connection('mysql')->table('fawtara_02')->where('uuid', $id)->get();
        $invoiceData->items = $items;

        // Choose the correct function based on invoice type
        $xml = null;

        switch (config('app.invoice_type')) {
            case 'sales':
                if ($invoiceData->{'invoice_type'} === '388') { // General Sales Invoice
                    $xml = $this->salesInvoiceService->generateGeneralSalesInvoiceXml($invoiceData);
                } elseif ($invoiceData->{'invoice_type'} === '381') { // Credit Invoice
                    $xml = $this->salesInvoiceService->generateCreditInvoiceXml($invoiceData);
                }
                break;
            case 'income':
                if ($invoiceData->{'invoice_type'} === '388') { // General Sales Invoice
                    $xml = $this->incomeInvoiceService->generateIncomeInvoiceXml($invoiceData);
                } elseif ($invoiceData->{'invoice_type'} === '381') { // Credit Invoice
                    $xml = $this->incomeInvoiceService->generateCreditIncomeInvoiceXml($invoiceData);
                }
                break;
            default:
                flash()->error("Unsupported invoice type configuration.");
                return redirect()->back();
        }

        if ($xml === null) {
            flash()->error("Invalid or unsupported invoice type for the configured invoice mode.");
            return redirect()->back();
        }

        // Create the folder for the invoice type
        $folderPath = $this->invoiceFileService->createFolder($invoiceData->{'invoice_type'});

        // Save the XML file in the correct folder
        $filePath = $this->invoiceFileService->saveInvoiceXml(
            $xml,
            $folderPath,
            $id,
            $invoiceData->{'invoice_type'},
            $invoiceData->{'invoicetypecode'}
        );

        // Send the XML to the API
        $response = $this->invoiceService->sendInvoiceToApi($filePath, $invoiceData->uuid);

        // Handle API response
        if ($response['status']) {
            flash()->success("Invoice successfully sent.");

            // Update invoice data
            DB::connection('mysql')
                ->table('fawtara_01')
                ->where('uuid', $id)
                ->update(['sent_to_fawtara' => 1, 'qr_code' => $response['data']['EINV_QR']]);

            DB::connection('mysql')
                ->table('fawtara_02')
                ->where('uuid', $id)
                ->update(['sent_to_fawtara' => 1]);

            // Generate and save the QR code using the QrcodeService
            $this->qrcodeService->generateQrCode($id, $response['data']['EINV_QR']);
        } else {
            flash()->error("Failed to send invoice: " . $response['message']);
        }

        return redirect()->back();
    }
}
