<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\AiAssistant\Contracts\AiGatewayInterface;
use App\Modules\AiAssistant\Services\DisabledAiGateway;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Policies\AppointmentPolicy;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Emergency\Policies\EmergencyTriagePolicy;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Policies\EncounterPolicy;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Ipd\Policies\IpdAdmissionPolicy;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Policies\LabOrderPolicy;
use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Pacs\Services\NullPacsGateway;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Policies\PatientPolicy;
use App\Modules\Ris\Policies\ImagingStudyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind future integration contracts with safe default implementations
        $this->app->singleton(PacsGatewayInterface::class, NullPacsGateway::class);
        $this->app->singleton(AiGatewayInterface::class, DisabledAiGateway::class);
    }

    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Encounter::class, EncounterPolicy::class);
        Gate::policy(EmergencyTriage::class, EmergencyTriagePolicy::class);
        Gate::policy(IpdAdmission::class, IpdAdmissionPolicy::class);
        Gate::policy(LabOrder::class, LabOrderPolicy::class);
        Gate::policy(ImagingStudy::class, ImagingStudyPolicy::class);
    }
}
