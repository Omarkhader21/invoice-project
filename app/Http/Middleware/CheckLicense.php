<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\LicenseService;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    public function handle(Request $request, Closure $next)
    {
        $this->licenseService->initialize(); // Ensure license file exists

        if (!$this->licenseService->isValid()) {
            abort(403, 'Application is locked. Please activate your license.');
        }

        return $next($request);
    }
}
