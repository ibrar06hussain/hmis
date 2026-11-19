<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\AiAssistant\Contracts\AiGatewayInterface;
use App\Modules\AiAssistant\Services\DisabledAiGateway;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Policies\AppointmentPolicy;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Policies\InvoicePolicy;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Emergency\Policies\EmergencyTriagePolicy;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Policies\EncounterPolicy;
use App\Modules\Hr\Models\DutyRoster;
use App\Modules\Hr\Policies\DutyRosterPolicy;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Ipd\Policies\IpdAdmissionPolicy;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Policies\LabOrderPolicy;
use App\Modules\Nursing\Models\NursingMarRecord;
use App\Modules\Nursing\Policies\NursingPolicy;
use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Pacs\Services\NullPacsGateway;
use App\Modules\Pacs\Services\OrthancPacsGateway;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Policies\PatientPolicy;
use App\Modules\Pharmacy\Models\Prescription;
use App\Modules\Pharmacy\Policies\PrescriptionPolicy;
use App\Modules\Reporting\Policies\ReportingPolicy;
use App\Modules\Reporting\Services\HospitalReportingService;
use App\Modules\Ris\Policies\ImagingStudyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dynamic PACS gateway binding: switches cleanly to Orthanc/OHIF when HMIS_PACS_ENABLED=true
        $this->app->bind(PacsGatewayInterface::class, function ($app): PacsGatewayInterface {
            if ((bool) config('hmis.pacs.enabled', false)) {
                return new OrthancPacsGateway($app->make(AuditLogger::class));
            }

            return new NullPacsGateway;
        });

        // AI remains strictly disabled by default
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
        Gate::policy(Prescription::class, PrescriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(NursingMarRecord::class, NursingPolicy::class);
        Gate::policy(DutyRoster::class, DutyRosterPolicy::class);
        Gate::policy(HospitalReportingService::class, ReportingPolicy::class);
    }
}
