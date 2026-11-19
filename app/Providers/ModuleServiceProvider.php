<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\AiAssistant\Contracts\AiGatewayInterface;
use App\Modules\AiAssistant\Services\DisabledAiGateway;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Policies\AppointmentPolicy;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Policies\EncounterPolicy;
use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Pacs\Services\NullPacsGateway;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Policies\PatientPolicy;
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
    }
}
