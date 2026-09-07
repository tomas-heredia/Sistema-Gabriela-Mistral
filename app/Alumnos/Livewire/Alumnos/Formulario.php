<?php

namespace App\Alumnos\Livewire\Alumnos;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use App\Alumnos\Models\Tutor;
use App\Boletines\Exceptions\PlantillaNoEncontradaException;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Services\CreadorDeBoletines;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Services\GeneradorDeCuotas;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulario extends Component
{
    public const VINCULOS = [
        'madre' => 'Madre',
        'padre' => 'Padre',
        'tutor_legal' => 'Tutor legal',
        'otro' => 'Otro',
    ];

    /**
     * Lista cerrada en vez de texto libre — evita errores de tipeo y
     * valores inconsistentes entre alumnos.
     */
    public const GRADOS_PRIMARIO = [
        '1er grado', '2do grado', '3er grado', '4to grado', '5to grado', '6to grado',
    ];

    public ?Alumno $alumno = null;

    public string $nombre = '';

    public ?string $dni = null;

    public string $fecha_nacimiento = '';

    public string $nivel = '';

    public string $grado = '';

    public ?int $anio_secundaria = null;

    public ?string $division = null;

    public ?string $libro_folio = null;

    public string $turno = '';

    public bool $activo = true;

    // Sección "Tutores vinculados" — solo aplica cuando el alumno ya existe.
    public string $dniTutorBuscado = '';

    public ?Tutor $tutorEncontrado = null;

    public bool $buscoTutor = false;

    public string $vinculoNuevo = 'madre';

    public bool $responsablePagoNuevo = false;

    public bool $becado = false;

    public string $motivoBeca = '';

    public function mount(?Alumno $alumno = null): void
    {
        if ($alumno?->exists) {
            $this->authorize('update', $alumno);

            $this->alumno = $alumno;
            $this->nombre = $alumno->nombre;
            $this->dni = $alumno->dni;
            $this->fecha_nacimiento = $alumno->fecha_nacimiento->format('Y-m-d');
            $this->nivel = $alumno->nivel->value;
            $this->grado = $alumno->grado;
            $this->anio_secundaria = $alumno->anio_secundaria;
            $this->division = $alumno->division;
            $this->libro_folio = $alumno->libro_folio;
            $this->turno = $alumno->turno->value;
            $this->activo = $alumno->activo;
        } else {
            $this->authorize('create', Alumno::class);
            $this->nivel = Nivel::Primario->value;
            $this->turno = Turno::Manana->value;
        }

        $becaActual = $this->becaDelPeriodoActivo();
        $this->becado = (bool) $becaActual;
        $this->motivoBeca = $becaActual?->motivo ?? '';
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'dni' => ['nullable', 'string', 'max:20', Rule::unique('alumnos', 'dni')->ignore($this->alumno?->id)],
            'fecha_nacimiento' => ['required', 'date'],
            'nivel' => ['required', Rule::enum(Nivel::class)],
            'grado' => [
                'required',
                'string',
                Rule::when($this->nivel === Nivel::Primario->value, [Rule::in(self::GRADOS_PRIMARIO)]),
            ],
            'anio_secundaria' => ['nullable', 'integer', 'min:1', 'max:6', 'required_if:nivel,'.Nivel::Secundario->value],
            'division' => ['nullable', 'string', 'max:10'],
            'libro_folio' => ['nullable', 'string', 'max:50'],
            'turno' => ['required', Rule::enum(Turno::class)],
            'activo' => ['boolean'],
        ];
    }

    /**
     * Al cambiar de nivel, "grado" no puede quedar con un valor que ya no
     * corresponde (ej. un grado de primaria si pasó a secundario).
     */
    public function updatedNivel(): void
    {
        $this->grado = $this->nivel === Nivel::Secundario->value
            ? $this->gradoDesdeAnioSecundaria()
            : '';
    }

    /**
     * El año de secundaria es la única fuente de verdad para el "grado" de
     * un alumno secundario — no hay un segundo campo de texto que pueda
     * quedar en contradicción con el año elegido.
     */
    public function updatedAnioSecundaria(): void
    {
        if ($this->nivel === Nivel::Secundario->value) {
            $this->grado = $this->gradoDesdeAnioSecundaria();
        }
    }

    private function gradoDesdeAnioSecundaria(): string
    {
        return $this->anio_secundaria ? "{$this->anio_secundaria}º año" : '';
    }

    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'dni' => 'DNI',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'nivel' => 'nivel',
            'grado' => 'grado',
            'anio_secundaria' => 'año',
            'division' => 'división',
            'libro_folio' => 'libro y folio',
            'turno' => 'turno',
        ];
    }

    public function guardar(): void
    {
        // Defensivo: el "grado" de un alumno secundario siempre se deriva
        // del año elegido, nunca de lo que haya quedado tipeado en el
        // navegador — evita que ambos campos queden en contradicción.
        if ($this->nivel === Nivel::Secundario->value) {
            $this->grado = $this->gradoDesdeAnioSecundaria();
        }

        $datos = $this->validate();

        if ($this->alumno) {
            $this->alumno->update($datos);
            session()->flash('mensaje', 'Alumno actualizado correctamente.');
            $this->redirectRoute('alumnos.editar', $this->alumno, navigate: true);

            return;
        }

        $nuevo = Alumno::create($datos);
        session()->flash('mensaje', 'Alumno creado correctamente. Ahora podés vincular sus tutores.');
        $this->redirectRoute('alumnos.editar', $nuevo, navigate: true);
    }

    public function buscarTutor(): void
    {
        $this->buscoTutor = true;
        $this->tutorEncontrado = Tutor::where('dni', $this->dniTutorBuscado)->first();
    }

    public function vincularTutor(): void
    {
        if (! $this->tutorEncontrado || ! $this->alumno) {
            return;
        }

        $yaVinculado = $this->alumno->tutores()->where('tutores.id', $this->tutorEncontrado->id)->exists();

        if ($yaVinculado) {
            $this->addError('dniTutorBuscado', 'Ese tutor ya está vinculado a este alumno.');

            return;
        }

        $this->alumno->tutores()->attach($this->tutorEncontrado->id, [
            'vinculo' => $this->vinculoNuevo,
            'responsable_pago' => $this->responsablePagoNuevo,
        ]);

        $this->reset(['dniTutorBuscado', 'tutorEncontrado', 'buscoTutor', 'vinculoNuevo', 'responsablePagoNuevo']);
        session()->flash('mensaje', 'Tutor vinculado correctamente.');
    }

    public function desvincularTutor(int $tutorId): void
    {
        $this->alumno->tutores()->detach($tutorId);
        session()->flash('mensaje', 'Tutor desvinculado.');
    }

    /**
     * Solo genera una vez: si el alumno ya tiene cuotas para el período
     * activo, no aparece ningún botón que las regenere.
     */
    public function generarCuotas(GeneradorDeCuotas $generador): void
    {
        $this->authorize('create', Cuota::class);

        $periodo = PeriodoLectivo::where('activo', true)->first();

        if (! $periodo || $this->tieneCuotas($periodo)) {
            return;
        }

        try {
            $generador->generar($this->alumno, $periodo);
            session()->flash('mensaje', 'Cuotas generadas correctamente.');
        } catch (ModelNotFoundException) {
            session()->flash('error', "No hay un arancel cargado para el nivel {$this->alumno->nivel->value} en el período {$periodo->nombre}. Cargalo antes de generar las cuotas.");
        }
    }

    private function tieneCuotas(PeriodoLectivo $periodo): bool
    {
        return Cuota::where('alumno_id', $this->alumno->id)
            ->where('periodo_lectivo_id', $periodo->id)
            ->exists();
    }

    /**
     * Igual que generarCuotas(): solo genera una vez, sin botón para
     * regenerar si el alumno ya tiene boletín para el período.
     */
    public function generarBoletin(CreadorDeBoletines $creador): void
    {
        $this->authorize('create', Boletin::class);

        $periodo = PeriodoLectivo::where('activo', true)->first();

        if (! $periodo || $this->boletinDelPeriodo($periodo)) {
            return;
        }

        try {
            $creador->crear($this->alumno, $periodo);
            session()->flash('mensaje', 'Libreta generada correctamente.');
        } catch (PlantillaNoEncontradaException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function boletinDelPeriodo(PeriodoLectivo $periodo): ?Boletin
    {
        return Boletin::with('trimestres')
            ->where('alumno_id', $this->alumno->id)
            ->where('periodo_lectivo_id', $periodo->id)
            ->first();
    }

    /**
     * La Etapa de Apoyo (trimestre 4) es opcional y solo existe en
     * primaria — a diferencia de los trimestres 1/2/3, nada la crea
     * automáticamente. Una vez creada, se carga y se envía exactamente
     * igual que cualquier otro trimestre (Cargar ya maneja el trimestre 4
     * de forma genérica).
     */
    public function agregarEtapaApoyo(): void
    {
        $periodo = PeriodoLectivo::where('activo', true)->first();
        $boletin = $periodo ? $this->boletinDelPeriodo($periodo) : null;

        if (! $boletin || $this->alumno->nivel !== Nivel::Primario) {
            return;
        }

        $this->authorize('update', $boletin);

        if ($boletin->trimestres->contains('trimestre', 4)) {
            return;
        }

        BoletinTrimestre::create(['boletin_id' => $boletin->id, 'trimestre' => 4]);
        session()->flash('mensaje', 'Etapa de Apoyo agregada.');
    }

    /**
     * Otorgar y revocar es una sola acción: tildar el checkbox y guardar da
     * de alta (o actualiza el motivo de) la beca del período activo;
     * destildarlo y guardar la borra. No recalcula cuotas ya generadas —
     * `GeneradorDeCuotas` solo evalúa la beca al momento de generar.
     */
    public function guardarBeca(): void
    {
        $periodo = PeriodoLectivo::where('activo', true)->first();

        if (! $periodo) {
            return;
        }

        $becaExistente = Beca::where('alumno_id', $this->alumno->id)->where('periodo_lectivo_id', $periodo->id)->first();

        if ($this->becado) {
            $this->authorize($becaExistente ? 'update' : 'create', $becaExistente ?? Beca::class);

            $this->validate(
                ['motivoBeca' => ['required', 'string', 'max:255']],
                attributes: ['motivoBeca' => 'motivo']
            );

            Beca::updateOrCreate(
                ['alumno_id' => $this->alumno->id, 'periodo_lectivo_id' => $periodo->id],
                ['motivo' => $this->motivoBeca, 'aprobado_por_id' => auth()->id(), 'fecha_otorgamiento' => now()]
            );

            session()->flash('mensaje', 'Beca otorgada correctamente.');

            return;
        }

        if ($becaExistente) {
            $this->authorize('delete', $becaExistente);
            $becaExistente->delete();
            session()->flash('mensaje', 'Beca revocada.');
        }
    }

    private function becaDelPeriodoActivo(): ?Beca
    {
        if (! $this->alumno) {
            return null;
        }

        $periodo = PeriodoLectivo::where('activo', true)->first();

        if (! $periodo) {
            return null;
        }

        return Beca::where('alumno_id', $this->alumno->id)->where('periodo_lectivo_id', $periodo->id)->first();
    }

    public function render()
    {
        $periodoActivo = PeriodoLectivo::where('activo', true)->first();

        return view('livewire.alumnos.alumnos.formulario', [
            'niveles' => Nivel::cases(),
            'turnos' => Turno::cases(),
            'tutoresVinculados' => $this->alumno?->tutores()->get(),
            'periodoActivo' => $periodoActivo,
            'cuotas' => $this->alumno && $periodoActivo
                ? Cuota::where('alumno_id', $this->alumno->id)->where('periodo_lectivo_id', $periodoActivo->id)->orderBy('mes')->get()
                : collect(),
            'boletin' => $this->alumno && $periodoActivo ? $this->boletinDelPeriodo($periodoActivo) : null,
            'beca' => $this->becaDelPeriodoActivo(),
        ]);
    }
}
