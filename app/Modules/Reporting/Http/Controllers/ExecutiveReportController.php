<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\HospitalReportingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExecutiveReportController extends Controller
{
    public function __construct(
        private readonly HospitalReportingService $reportingService,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewExecutive', HospitalReportingService::class);

        return view('modules.reporting.index', [
            'summary' => $this->reportingService->executiveSummary(),
        ]);
    }
}
