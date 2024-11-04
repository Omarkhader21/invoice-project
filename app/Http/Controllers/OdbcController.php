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
            $results = DB::connection('odbc')->table('fawtara-01')->get();
            // If the query is successful, return success
            if ($results) {
                return response()->json(['status' => 'success']);
            }
        } catch (\Exception $e) {
            // Catch any errors and return an error message
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
