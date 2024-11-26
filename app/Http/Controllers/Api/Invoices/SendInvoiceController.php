<?php

namespace App\Http\Controllers\Api\Invoices;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Services\InvoiceFileService;
use Illuminate\Support\Facades\DB;

class SendInvoiceController extends Controller
{
    protected $invoiceService;
    protected $invoiceFileService;

    public function __construct(InvoiceService $invoiceService, InvoiceFileService $invoiceFileService)
    {
        $this->invoiceService = $invoiceService;
        $this->invoiceFileService = $invoiceFileService;
    }

    public function sendInvoice($id)
    {
        // Fetch invoice data
        $invoiceData = DB::connection(name: "odbc")->select("SELECT * FROM [fawtara-01] WHERE [uuid] = ?", [$id]);

        if (empty($invoiceData)) {
            flash()->error("The invoice does not exist.");
            return back();
        }

        [$invoiceData] = $invoiceData; // Extract the first (and only) record

        // Fetch related items
        $items = DB::connection("odbc")->table("fawtara-02")->where("uuid", $id)->get();
        $invoiceData->items = $items;

        // Choose the correct function based on invoice type
        $xml = null;

        if ($invoiceData->{'invoice-type'} === '388') { // General Sales Invoice
            $xml = $this->invoiceService->generateGeneralSalesInvoiceXml($invoiceData);
        } elseif ($invoiceData->{'invoice-type'} === '381') { // Credit Invoice
            $xml = $this->invoiceService->generateCreditInvoiceXml($invoiceData);
        } else {
            flash()->error("Unsupported invoice type.");
            return back();
        }

        // Create the folder for the invoice type
        $folderPath = $this->invoiceFileService->createFolder($invoiceData->{'invoice-type'});

        // Save the XML file in the correct folder
        $filePath = $this->invoiceFileService->saveInvoiceXml($xml, $folderPath, $id, $invoiceData->{'invoice-type'});

        $response = $this->invoiceService->sendInvoiceToApi($filePath, $invoiceData->uuid);

        if ($response['status']) {
            flash()->success("Invoice successfully sent.");
        } else {
            flash()->error("Failed to send invoice: " . $response['message']);
        }

        return redirect()->back();
    }
}
