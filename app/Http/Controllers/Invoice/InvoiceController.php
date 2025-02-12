<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
