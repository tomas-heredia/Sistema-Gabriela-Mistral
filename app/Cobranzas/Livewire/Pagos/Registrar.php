<?php

namespace App\Cobranzas\Livewire\Pagos;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Exceptions\AsignacionDePagoInvalidaException;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\MedioPago;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Services\AsignadorDePagos;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * El monto del pago no se pide aparte: se calcula solo, como la suma de
 * las cuotas tildadas. Elimina de raíz la posibilidad de que "lo que se
 * cobró" y "lo aplicado a cuotas" no coincidan — resuelve, con la misma
 * pantalla, pago parcial (tildás una cuota y bajás el monto) y pago que
 * cubre más de un mes (tildás varias).
 */
#[Layout('layouts.app')]
class Registrar extends Component
{
    public string $dniTutorBuscado = '';

    public ?Tutor $tutorEncontrado = null;

    public bool $buscoTutor = false;

    /** @var array<int, bool> [cuota_id => seleccionada] */
    public array $cuotasSeleccionadas = [];

    /** @var array<int, string> [cuota_id => monto en pesos, como texto] */
    public array $montos = [];

    public string $medio_pago = '';

    public string $fecha = '';

    public string $numero_recibo = '';

    public ?string $observaciones = null;

    public function mount(): void
    {
        $this->authorize('create', Pago::class);
        $this->fecha = now()->format('Y-m-d');
    }

    public function buscarTutor(): void
    {
        $this->buscoTutor = true;
        $this->tutorEncontrado = Tutor::where('dni', $this->dniTutorBuscado)->first();
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
                $total += $this->pesosACentavos($this->montos[$cuotaId] ?? '0');
            }
        }

        return $total;
    }

    public function guardar(AsignadorDePagos $asignador): void
    {
        $this->validate([
            'medio_pago' => ['required', Rule::enum(MedioPago::class)],
            'fecha' => ['required', 'date'],
            'numero_recibo' => ['required', 'string', 'max:255', 'unique:pagos,numero_recibo'],
        ]);

        $asignaciones = [];

        foreach ($this->cuotasSeleccionadas as $cuotaId => $seleccionada) {
            if (! $seleccionada) {
                continue;
            }

            $monto = $this->pesosACentavos($this->montos[$cuotaId] ?? '0');

            if ($monto > 0) {
                $asignaciones[$cuotaId] = $monto;
            }
        }

        if (empty($asignaciones)) {
            $this->addError('cuotasSeleccionadas', 'Seleccioná al menos una cuota para aplicar el pago.');

            return;
        }

        try {
            DB::transaction(function () use ($asignaciones, $asignador) {
                $pago = Pago::create([
                    'tutor_id' => $this->tutorEncontrado->id,
                    'monto' => array_sum($asignaciones),
                    'medio_pago' => $this->medio_pago,
                    'fecha' => $this->fecha,
                    'numero_recibo' => $this->numero_recibo,
                    'cobrador_id' => auth()->id(),
                    'observaciones' => $this->observaciones,
                ]);

                $asignador->aplicar($pago, $asignaciones);
            });
        } catch (AsignacionDePagoInvalidaException $excepcion) {
            $this->addError('cuotasSeleccionadas', $excepcion->getMessage());

            return;
        }

        session()->flash('mensaje', 'Pago registrado correctamente.');
        $this->redirectRoute('pagos.index', navigate: true);
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
