<?php

namespace App\Http\Controllers\Api\Invoices;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Services\InvoiceFileService;
use App\Services\QrcodeService;
use Illuminate\Support\Facades\DB;

class SendInvoiceController extends Controller
{
    protected $invoiceService;
    protected $invoiceFileService;
    protected $qrcodeService;

    public function __construct(InvoiceService $invoiceService, InvoiceFileService $invoiceFileService, QrcodeService $qrcodeService)
    {
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

        if (empty($invoiceData)) {
            flash()->error("Invoice not found or already sent.");
            return redirect()->back();
        }

        if (empty($invoiceData)) {
            flash()->error("The invoice does not exist.");
            return back();
        }

        [$invoiceData] = $invoiceData; // Extract the first (and only) record

        // Fetch related items
        $items = DB::connection("mysql")->table("fawtara_02")->where("uuid", $id)->get();
        $invoiceData->items = $items;

        // Choose the correct function based on invoice type
        $xml = null;

        if ($invoiceData->{'invoice_type'} === '388') { // General Sales Invoice
            $xml = $this->invoiceService->generateGeneralSalesInvoiceXml($invoiceData);
        } elseif ($invoiceData->{'invoice_type'} === '381') { // Credit Invoice
            $xml = $this->invoiceService->generateCreditInvoiceXml($invoiceData);
        } else {
            flash()->error("Unsupported invoice type.");
            return back();
        }

        // Create the folder for the invoice type
        $folderPath = $this->invoiceFileService->createFolder($invoiceData->{'invoice_type'});

        // Save the XML file in the correct folder
        $filePath = $this->invoiceFileService->saveInvoiceXml($xml, $folderPath, $id, $invoiceData->{'invoice_type'});

        // Send the XML to the API
        $response = $this->invoiceService->sendInvoiceToApi($filePath, $invoiceData->uuid);

        // Handle API response
        if ($response['status']) {

            flash()->success("Invoice successfully sent.");
            // Fetch invoice data
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
