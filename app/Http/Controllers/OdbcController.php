<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OdbcController extends Controller
{
    public function testOdbcConnection()
    {
        try {
            // Attempt the query using the ODBC connection
            $results = DB::connection('mysql')->table('fawtara_01')->get();
            // If the query is successful, return success
            if ($results) {
                return response()->json(['name' => $results]);
            }
        } catch (\Exception $e) {
            // Catch any errors and return an error message
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
