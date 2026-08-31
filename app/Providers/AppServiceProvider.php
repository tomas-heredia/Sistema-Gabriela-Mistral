<?php

namespace App\Providers;

use App\Alumnos\Livewire\Alumnos\Formulario as AlumnosFormulario;
use App\Alumnos\Livewire\Alumnos\Listado as AlumnosListado;
use App\Alumnos\Livewire\Tutores\Formulario as TutoresFormulario;
use App\Alumnos\Livewire\Tutores\Listado as TutoresListado;
use App\Cobranzas\Livewire\Pagos\Listado as PagosListado;
use App\Cobranzas\Livewire\Pagos\Registrar as PagosRegistrar;
use App\Cobranzas\Models\Observers\PagoCuotaObserver;
use App\Cobranzas\Models\PagoCuota;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PagoCuota::observe(PagoCuotaObserver::class);

        Livewire::component('tutores.listado', TutoresListado::class);
        Livewire::component('tutores.formulario', TutoresFormulario::class);
        Livewire::component('alumnos.listado', AlumnosListado::class);
        Livewire::component('alumnos.formulario', AlumnosFormulario::class);
        Livewire::component('pagos.listado', PagosListado::class);
        Livewire::component('pagos.registrar', PagosRegistrar::class);
    }
}
