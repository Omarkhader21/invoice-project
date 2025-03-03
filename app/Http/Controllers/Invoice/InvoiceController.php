<?php

namespace App\Http\Controllers\Invoice;

use Exception;
use Illuminate\Http\Request;
use App\Services\QrcodeService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class InvoiceController extends Controller
{
    protected $qrcodeService;

    public function __construct()
    {
        $this->qrcodeService = new QrcodeService();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Fetch all records from Access directly
            $rawInvoices = DB::connection('mysql')->table('fawtara_01')->get();

            // Manual Pagination
            $currentPage = $request->input('page', 1);
            $perPage = 5;
            $offset = ($currentPage - 1) * $perPage;

            // Paginate the collection
            $paginatedInvoices = new \Illuminate\Pagination\LengthAwarePaginator(
                $rawInvoices->slice($offset, $perPage),
                $rawInvoices->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return view('invoice.index', compact('paginatedInvoices'));
        } catch (Exception $e) {
            flash()->error($e->getMessage());
            return redirect()->route('dashboard');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $details = DB::connection('mysql')->table('fawtara_02')->where('uuid', $id)->get();
            return view('invoice.show',  compact('details'));
        } catch (Exception $e) {
            flash()->error($e->getMessage());
            return redirect()->route('dashboard');
        }
    }

    /**
     * Generate QrCode.
     */
    public function generateQrCode(string $id)
    {
        try {
            // Retrieve the invoice from the database
            $invoice = DB::connection('mysql')->table('fawtara_01')
                ->where('uuid', $id)
                ->select('qr_code')
                ->first();

            // If the invoice is not found, show an error
            if (!$invoice) {
                flash()->error('Invoice not found.');
                return redirect()->back();
            }

            // Generate and save the QR code using the QrcodeService
            $qrCodePath = $this->qrcodeService->generateQrCode($id, $invoice->qr_code);

            // Flash success message and return the QR code file for download
            flash()->success('QR Code generated successfully!');
            return response()->download($qrCodePath);
        } catch (Exception $e) {
            // If any error occurs, catch it and display the error message
            flash()->error($e->getMessage());
            return redirect()->back();
        }
    }
}
