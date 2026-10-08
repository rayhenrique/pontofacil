<?php

namespace App\Providers;

use App\Domain\Compliance\Signing\CadesSignatureVerifierInterface;
use App\Domain\Compliance\Signing\DevSigningService;
use App\Domain\Compliance\Signing\PendingCadesSignatureVerifier;
use App\Domain\Compliance\Signing\SigningServiceInterface;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Observers\CompanyObserver;
use App\Observers\EmployeeObserver;
use App\Observers\EstablishmentObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            SigningServiceInterface::class,
            DevSigningService::class
        );

        $this->app->bind(
            CadesSignatureVerifierInterface::class,
            PendingCadesSignatureVerifier::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Carbon::setLocale('pt_BR');
        setlocale(LC_TIME, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Establishment::observe(EstablishmentObserver::class);
        Company::observe(CompanyObserver::class);
        Employee::observe(EmployeeObserver::class);
    }
}
