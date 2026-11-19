<?php

declare(strict_types=1);

namespace App\Modules\Icu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Icu\Models\IcuDeviceObservation;
use App\Modules\Icu\Services\IcuTelemetryService;
use App\Modules\Nursing\Models\NursingMarRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IcuTelemetryController extends Controller
{
    public function __construct(
        private readonly IcuTelemetryService $telemetryService,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewWorkbench', NursingMarRecord::class);

        $observations = IcuDeviceObservation::query()
            ->with(['patient', 'bed.ward', 'verifiedBy'])
            ->orderByDesc('observed_at')
            ->limit(60)
            ->get();

        return view('modules.icu.index', [
            'observations' => $observations,
        ]);
    }

    public function verify(Request $request, IcuDeviceObservation $observation): RedirectResponse
    {
        Gate::authorize('record', NursingMarRecord::class);

        /** @var User $user */
        $user = $request->user();
        $this->telemetryService->verify($observation, $user);

        return back()->with('status', "Telemetry reading ({$observation->observation_type}) verified into clinical record.");
    }

    public function rejectArtifact(Request $request, IcuDeviceObservation $observation): RedirectResponse
    {
        Gate::authorize('record', NursingMarRecord::class);

        /** @var User $user */
        $user = $request->user();
        $this->telemetryService->rejectArtifact($observation, $user);

        return back()->with('warning', "Telemetry reading ({$observation->observation_type}) flagged and rejected as artifact.");
    }
}
