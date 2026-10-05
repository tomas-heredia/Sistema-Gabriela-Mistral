<?php

namespace App\Cobranzas\Livewire\Pagos;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Exceptions\AsignacionDePagoInvalidaException;
use App\Cobranzas\Jobs\EnviarComprobantePago;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\MedioPago;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Services\AsignadorDePagos;
use App\Cobranzas\Services\GeneradorDeComprobantePago;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * El monto del pago no se pide aparte: se calcula solo, como la suma de
 * las cuotas tildadas más el interés. Elimina de raíz la posibilidad de que
 * "lo que se cobró" y "lo aplicado a cuotas" no coincidan — resuelve, con la
 * misma pantalla, pago parcial (tildás una cuota y bajás el monto) y pago
 * que cubre más de un mes (tildás varias).
 */
#[Layout('layouts.app')]
class Registrar extends Component
{
    public string $busquedaTutor = '';

    public ?Tutor $tutorEncontrado = null;

    public bool $buscoTutor = false;

    /**
     * Coincidencias cuando la búsqueda por nombre trae más de un tutor --
     * hay que elegir cuál antes de ver sus cuotas. Queda vacío apenas hay
     * un único resultado (se selecciona solo) o ninguno.
     *
     * @var array<int, array{id:int, nombre:string, dni:string}>
     */
    public array $resultadosBusqueda = [];

    /** @var array<int, bool> [cuota_id => seleccionada] */
    public array $cuotasSeleccionadas = [];

    /** @var array<int, string> [cuota_id => monto en pesos, como texto] */
    public array $montos = [];

    public string $medio_pago = '';

    public string $fecha = '';

    /**
     * Porcentaje único para toda la operación -- se aplica por separado
     * sobre la deuda de cada cuota tildada, no sobre el total combinado.
     */
    public string $interesPorcentaje = '';

    public ?string $observaciones = null;

    /**
     * Comprobantes recién generados, para mostrar los links de descarga sin
     * salir de la pantalla (el cobrador los necesita al toque, no puede
     * esperar a buscarlos después en el listado).
     *
     * @var array<int, array{numeroRecibo: string, alumno: string, periodo: string, url: string}>
     */
    public array $comprobantesGenerados = [];

    public function mount(): void
    {
        $this->authorize('create', Pago::class);
        $this->fecha = now()->format('Y-m-d');
    }

    /**
     * Busca por DNI exacto, o por coincidencia parcial en el nombre del
     * tutor o en el de alguno de sus alumnos -- el cobrador no siempre
     * tiene el DNI a mano, pero sabe el apellido de la familia o del
     * chico. Con un único resultado se selecciona solo; con varios, hay
     * que elegir cuál antes de ver sus cuotas.
     */
    public function buscarTutor(): void
    {
        $this->buscoTutor = true;
        $this->tutorEncontrado = null;
        $this->resultadosBusqueda = [];
        $this->cuotasSeleccionadas = [];
        $this->montos = [];
        $this->comprobantesGenerados = [];

        if ($this->busquedaTutor === '') {
            return;
        }

        $coincidencias = Tutor::query()
            ->where(function ($query) {
                $query->where('dni', $this->busquedaTutor)
                    ->orWhere('nombre', 'like', "%{$this->busquedaTutor}%")
                    ->orWhereHas('alumnos', fn ($q) => $q->where('nombre', 'like', "%{$this->busquedaTutor}%"));
            })
            ->orderBy('nombre')
            ->get();

        if ($coincidencias->count() === 1) {
            $this->seleccionarTutor($coincidencias->first()->id);

            return;
        }

        $this->resultadosBusqueda = $coincidencias
            ->map(fn (Tutor $tutor) => ['id' => $tutor->id, 'nombre' => $tutor->nombre, 'dni' => $tutor->dni])
            ->all();
    }

    public function seleccionarTutor(int $tutorId): void
    {
        $this->tutorEncontrado = Tutor::find($tutorId);
        $this->resultadosBusqueda = [];
        $this->cuotasSeleccionadas = [];
        $this->montos = [];

        if (! $this->tutorEncontrado) {
            return;
        }

        foreach ($this->cuotasDelTutor() as $cuota) {
            $this->montos[$cuota->id] = number_format($this->saldoPendiente($cuota) / 100, 2, '.', '');
        }
    }

    public function getMontoTotalProperty(): int
    {
        $total = 0;

        foreach ($this->cuotasSeleccionadas as $cuotaId => $seleccionada) {
            if ($seleccionada) {
                $monto = $this->pesosACentavos($this->montos[$cuotaId] ?? '0');
                $total += $monto + $this->interesSobre($monto);
            }
        }

        return $total;
    }

    public function guardar(AsignadorDePagos $asignador, GeneradorDeComprobantePago $generador): void
    {
        $this->validate([
            'medio_pago' => ['required', Rule::enum(MedioPago::class)],
            'fecha' => ['required', 'date'],
            'interesPorcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $asignaciones = [];

        foreach ($this->cuotasSeleccionadas as $cuotaId => $seleccionada) {
            if (! $seleccionada) {
                continue;
            }

            $monto = $this->pesosACentavos($this->montos[$cuotaId] ?? '0');

            if ($monto > 0) {
                $asignaciones[$cuotaId] = ['monto' => $monto, 'interes' => $this->interesSobre($monto)];
            }
        }

        if (empty($asignaciones)) {
            $this->addError('cuotasSeleccionadas', 'Seleccioná al menos una cuota para aplicar el pago.');

            return;
        }

        $montoTotal = array_sum(array_map(fn ($a) => $a['monto'] + $a['interes'], $asignaciones));

        $pago = null;

        try {
            DB::transaction(function () use ($asignaciones, $asignador, $montoTotal, &$pago) {
                $pago = Pago::create([
                    'tutor_id' => $this->tutorEncontrado->id,
                    'monto' => $montoTotal,
                    'medio_pago' => $this->medio_pago,
                    'interes_porcentaje' => $this->interesPorcentaje !== '' ? $this->interesPorcentaje : 0,
                    'fecha' => $this->fecha,
                    'cobrador_id' => auth()->id(),
                    'observaciones' => $this->observaciones,
                ]);

                $asignador->aplicar($pago, $asignaciones);
            });
        } catch (AsignacionDePagoInvalidaException $excepcion) {
            $this->addError('cuotasSeleccionadas', $excepcion->getMessage());

            return;
        }

        $pagoCuotas = $pago->pagoCuotas()->with(['cuota.alumno', 'cuota.periodoLectivo'])->get();

        $this->comprobantesGenerados = $pagoCuotas->map(function ($pagoCuota) use ($generador) {
            $generador->generar($pagoCuota);
            EnviarComprobantePago::dispatch($pagoCuota);

            return [
                'numeroRecibo' => $pagoCuota->numero_recibo,
                'alumno' => $pagoCuota->cuota->alumno->nombre,
                'periodo' => str_pad((string) $pagoCuota->cuota->mes, 2, '0', STR_PAD_LEFT).'/'.$pagoCuota->cuota->periodoLectivo->nombre,
                'url' => route('pagos.comprobantes.descargar', $pagoCuota),
            ];
        })->all();

        session()->flash('mensaje', 'Pago registrado correctamente.');
        $this->reset(['tutorEncontrado', 'cuotasSeleccionadas', 'montos', 'medio_pago', 'interesPorcentaje', 'observaciones', 'busquedaTutor', 'buscoTutor']);
    }

    /**
     * Vuelve a la búsqueda de tutor, limpiando los comprobantes del pago
     * anterior -- para cuando el cobrador va a cargar el pago de otra
     * familia sin salir de la pantalla.
     */
    public function nuevoPago(): void
    {
        $this->comprobantesGenerados = [];
    }

    private function interesSobre(int $monto): int
    {
        $porcentaje = $this->interesPorcentaje !== '' ? (float) $this->interesPorcentaje : 0.0;

        return (int) round($monto * $porcentaje / 100);
    }

    /**
     * @return Collection<int, Cuota>
     */
    private function cuotasDelTutor(): Collection
    {
        if (! $this->tutorEncontrado) {
            return collect();
        }

        $periodo = PeriodoLectivo::where('activo', true)->first();

        if (! $periodo) {
            return collect();
        }

        $alumnoIds = $this->tutorEncontrado->alumnos()->pluck('alumnos.id');

        return Cuota::whereIn('alumno_id', $alumnoIds)
            ->whereIn('estado', [EstadoCuota::Pendiente, EstadoCuota::Parcial])
            ->where('periodo_lectivo_id', $periodo->id)
            ->with('alumno')
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    private function saldoPendiente(Cuota $cuota): int
    {
        return $cuota->monto - $cuota->montoPagado();
    }

    private function pesosACentavos(string $pesos): int
    {
        return (int) round(((float) $pesos) * 100);
    }

    public function render()
    {
        return view('livewire.cobranzas.pagos.registrar', [
            'cuotas' => $this->cuotasDelTutor(),
            'medios' => MedioPago::cases(),
        ]);
    }
}
