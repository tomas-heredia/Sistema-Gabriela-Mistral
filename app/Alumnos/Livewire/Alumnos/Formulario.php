<?php

namespace App\Alumnos\Livewire\Alumnos;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use App\Alumnos\Models\Tutor;
use App\Boletines\Exceptions\PlantillaNoEncontradaException;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\Enums\EstadoBoletin;
use App\Boletines\Services\CreadorDeBoletines;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Services\GeneradorDeCuotas;
use App\Cobranzas\Services\RecalculadorDeCuotas;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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

    /**
     * Nivel inicial (jardín): solo para control administrativo del alumno y
     * la mora de su tutor -- nunca se les carga libreta (ver
     * generarBoletin() y la vista, que ocultan esa sección para este nivel).
     */
    public const GRADOS_INICIAL = ['Sala de 3', 'Sala de 4', 'Sala de 5'];

    public ?Alumno $alumno = null;

    public string $nombre = '';

    public ?string $dni = null;

    public string $fecha_nacimiento = '';

    public string $nivel = '';

    public string $grado = '';

    public ?int $anio_secundaria = null;

    public ?string $division = null;

    public string $turno = '';

    public bool $activo = true;

    // Sección "Tutores".
    public string $dniTutorBuscado = '';

    public ?Tutor $tutorEncontrado = null;

    public bool $buscoTutor = false;

    public string $vinculoNuevo = 'madre';

    public bool $responsablePagoNuevo = false;

    // Alta de un tutor nuevo, sin salir de esta pantalla, para cuando la
    // búsqueda por DNI no encuentra a nadie.
    public bool $creandoTutorNuevo = false;

    public string $nombreTutorACrear = '';

    public string $dniTutorACrear = '';

    public string $domicilioTutorACrear = '';

    public string $telefonoTutorACrear = '';

    public string $correoTutorACrear = '';

    /**
     * Tutores elegidos durante el alta, todavía no guardados — el alumno
     * no existe hasta que se llama a guardar(), así que no hay a qué
     * pivote engancharlos todavía. Se adjuntan recién dentro de la misma
     * transacción que crea el alumno.
     *
     * @var array<int, array{tutor_id:int, nombre:string, vinculo:string, responsable_pago:bool}>
     */
    public array $tutoresPendientes = [];

    public bool $becado = false;

    public string $motivoBeca = '';

    // Confirmación de alcance al revocar una beca (ver guardarBeca()).
    public bool $confirmandoRevocarBeca = false;

    public string $alcanceRevocarBeca = 'periodo';

    public string $fechaDesdeRevocarBeca = '';

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
            'dni' => ['required', 'digits_between:1,20', Rule::unique('alumnos', 'dni')->ignore($this->alumnoInactivoConMismoDni()?->id ?? $this->alumno?->id)],
            'fecha_nacimiento' => ['required', 'date'],
            'nivel' => ['required', Rule::enum(Nivel::class)],
            'grado' => [
                'required',
                'string',
                Rule::when($this->nivel === Nivel::Primario->value, [Rule::in(self::GRADOS_PRIMARIO)]),
                Rule::when($this->nivel === Nivel::Inicial->value, [Rule::in(self::GRADOS_INICIAL)]),
            ],
            'anio_secundaria' => ['nullable', 'integer', 'min:1', 'max:6', 'required_if:nivel,'.Nivel::Secundario->value],
            'division' => ['nullable', 'string', 'max:10', 'required_if:nivel,'.Nivel::Secundario->value],
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

    /**
     * Solo tiene sentido en el alta (editando un alumno existente, el DNI
     * ya es el suyo). Se usa para que la validación unique no rechace un
     * DNI que coincide con un alumno inactivo -- ver guardar(), que
     * reactiva ese registro en vez de crear uno nuevo.
     */
    private function alumnoInactivoConMismoDni(): ?Alumno
    {
        if ($this->alumno) {
            return null;
        }

        return Alumno::where('dni', $this->dni)->where('activo', false)->first();
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

        if (empty($this->tutoresPendientes)) {
            $this->addError('tutoresPendientes', 'Vinculá al menos un tutor antes de guardar.');

            return;
        }

        $alumnoInactivo = $this->alumnoInactivoConMismoDni();

        $nuevo = DB::transaction(function () use ($datos, $alumnoInactivo) {
            if ($alumnoInactivo) {
                $alumnoInactivo->update(array_merge($datos, ['activo' => true]));
                $alumno = $alumnoInactivo;
            } else {
                $alumno = Alumno::create($datos);
            }

            foreach ($this->tutoresPendientes as $pendiente) {
                // Si el alumno es un reactivado, puede que ya tuviera este
                // tutor vinculado de antes de desactivarse -- no duplicar
                // la fila de la tabla pivote.
                $yaVinculado = $alumno->tutores()->where('tutores.id', $pendiente['tutor_id'])->exists();

                if (! $yaVinculado) {
                    $alumno->tutores()->attach($pendiente['tutor_id'], [
                        'vinculo' => $pendiente['vinculo'],
                        'responsable_pago' => $pendiente['responsable_pago'],
                    ]);
                }
            }

            return $alumno;
        });

        session()->flash('mensaje', $alumnoInactivo ? 'Alumno reactivado correctamente.' : 'Alumno creado correctamente.');
        $this->redirectRoute('alumnos.editar', $nuevo, navigate: true);
    }

    public function buscarTutor(): void
    {
        $this->buscoTutor = true;
        $this->tutorEncontrado = Tutor::where('dni', $this->dniTutorBuscado)->first();
    }

    /**
     * Con alumno existente, vincula directo en la base. En el alta (todavía
     * sin alumno) solo agrega a la lista pendiente — recién se adjunta de
     * verdad cuando guardar() crea el alumno.
     */
    public function vincularTutor(): void
    {
        if (! $this->tutorEncontrado) {
            return;
        }

        if (! $this->vincularOEncolar($this->tutorEncontrado)) {
            return;
        }

        $this->reset(['dniTutorBuscado', 'tutorEncontrado', 'buscoTutor', 'vinculoNuevo', 'responsablePagoNuevo']);
    }

    /**
     * Muestra el mini-formulario de alta de tutor, para cuando la búsqueda
     * por DNI no encuentra a nadie — sin salir de la pantalla ni perder lo
     * ya cargado del alumno. Precarga el DNI ya tipeado en la búsqueda.
     */
    public function mostrarCrearTutor(): void
    {
        $this->authorize('create', Tutor::class);

        $this->creandoTutorNuevo = true;
        $this->dniTutorACrear = $this->dniTutorBuscado;
    }

    public function cancelarCrearTutor(): void
    {
        $this->creandoTutorNuevo = false;
        $this->reset(['nombreTutorACrear', 'dniTutorACrear', 'domicilioTutorACrear', 'telefonoTutorACrear', 'correoTutorACrear']);
    }

    /**
     * Mismas reglas que App\Alumnos\Livewire\Tutores\Formulario -- un tutor
     * creado desde acá tiene que quedar tan completo como uno creado desde
     * su propia pantalla.
     */
    public function crearYVincularTutor(): void
    {
        $this->authorize('create', Tutor::class);

        $datos = $this->validate([
            'nombreTutorACrear' => ['required', 'string', 'max:255'],
            'dniTutorACrear' => ['required', 'digits_between:1,20', Rule::unique('tutores', 'dni')],
            'domicilioTutorACrear' => ['required', 'string', 'max:255'],
            'telefonoTutorACrear' => ['required', 'digits_between:1,30'],
            'correoTutorACrear' => ['required', 'email', 'max:255'],
        ], attributes: [
            'nombreTutorACrear' => 'nombre',
            'dniTutorACrear' => 'DNI',
            'domicilioTutorACrear' => 'domicilio',
            'telefonoTutorACrear' => 'teléfono',
            'correoTutorACrear' => 'correo',
        ]);

        $tutor = Tutor::create([
            'nombre' => $datos['nombreTutorACrear'],
            'dni' => $datos['dniTutorACrear'],
            'domicilio' => $datos['domicilioTutorACrear'],
            'telefono' => $datos['telefonoTutorACrear'],
            'correo' => $datos['correoTutorACrear'],
        ]);

        if (! $this->vincularOEncolar($tutor)) {
            return;
        }

        $this->creandoTutorNuevo = false;
        $this->reset([
            'dniTutorBuscado', 'tutorEncontrado', 'buscoTutor', 'vinculoNuevo', 'responsablePagoNuevo',
            'nombreTutorACrear', 'dniTutorACrear', 'domicilioTutorACrear', 'telefonoTutorACrear', 'correoTutorACrear',
        ]);
    }

    private function vincularOEncolar(Tutor $tutor): bool
    {
        if ($this->alumno) {
            $yaVinculado = $this->alumno->tutores()->where('tutores.id', $tutor->id)->exists();

            if ($yaVinculado) {
                $this->addError('dniTutorBuscado', 'Ese tutor ya está vinculado a este alumno.');

                return false;
            }

            $this->alumno->tutores()->attach($tutor->id, [
                'vinculo' => $this->vinculoNuevo,
                'responsable_pago' => $this->responsablePagoNuevo,
            ]);

            session()->flash('mensaje', 'Tutor vinculado correctamente.');

            return true;
        }

        if (collect($this->tutoresPendientes)->contains('tutor_id', $tutor->id)) {
            $this->addError('dniTutorBuscado', 'Ese tutor ya está agregado.');

            return false;
        }

        $this->tutoresPendientes[] = [
            'tutor_id' => $tutor->id,
            'nombre' => $tutor->nombre,
            'vinculo' => $this->vinculoNuevo,
            'responsable_pago' => $this->responsablePagoNuevo,
        ];

        return true;
    }

    public function quitarTutorPendiente(int $indice): void
    {
        unset($this->tutoresPendientes[$indice]);
        $this->tutoresPendientes = array_values($this->tutoresPendientes);
    }

    /**
     * Un alumno necesita al menos un tutor vinculado en todo momento, no
     * solo al crearlo -- si no, pagos/libretas/avisos de mora se siguen
     * generando pero no le llegan a nadie, en silencio.
     */
    public function desvincularTutor(int $tutorId): void
    {
        if ($this->alumno->tutores()->count() <= 1) {
            session()->flash('error', 'El alumno necesita al menos un tutor vinculado — no se puede quitar el último.');

            return;
        }

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

        // Nivel inicial nunca tiene libreta -- no hay plantilla para ese
        // nivel ni tiene sentido que la tenga (ver GRADOS_INICIAL).
        if ($this->alumno->nivel === Nivel::Inicial) {
            return;
        }

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
     * Una libreta todavía en curso de un período que ya no es el activo --
     * ej. esperando la recuperación de febrero mientras ya arrancó el
     * ciclo lectivo siguiente. Se muestra aparte de la libreta del período
     * activo (no en su lugar) para no perderla de vista justo cuando el
     * ciclo lectivo cambia.
     */
    private function boletinPendienteDeOtroPeriodo(?PeriodoLectivo $periodoActivo): ?Boletin
    {
        return Boletin::with('trimestres')
            ->where('alumno_id', $this->alumno->id)
            ->where('estado', EstadoBoletin::EnCurso)
            ->when($periodoActivo, fn ($query) => $query->where('periodo_lectivo_id', '!=', $periodoActivo->id))
            ->first();
    }

    /**
     * Otorgar: tildar el checkbox y guardar da de alta (o actualiza el
     * motivo de) la beca del período activo -- no recalcula cuotas ya
     * generadas, `GeneradorDeCuotas` solo evalúa la beca al momento de
     * generar. Revocar: destildarlo y guardar abre el panel de
     * confirmación de confirmarRevocarBeca() en vez de borrar directo, para
     * elegir qué cuotas ya generadas con este descuento se recalculan.
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
                ['motivoBeca' => ['nullable', 'string', 'max:255']],
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
            // No se borra todavía: primero hay que elegir qué cuotas ya
            // generadas con este descuento se recalculan (ver
            // confirmarRevocarBeca()).
            $this->confirmandoRevocarBeca = true;
        }
    }

    /**
     * Las cuotas pendientes que ya se generaron con esta beca quedaron en
     * $0 -- al revocarla tienen que volver a cobrarse. El cobrador/admin
     * elige si eso aplica a todas las pendientes del período, o solo desde
     * una fecha de vencimiento en adelante (ej. si los meses ya vencidos
     * se los perdonan, pero los que faltan sí se cobran).
     */
    public function confirmarRevocarBeca(RecalculadorDeCuotas $recalculador): void
    {
        $periodo = PeriodoLectivo::where('activo', true)->first();
        $becaExistente = $periodo
            ? Beca::where('alumno_id', $this->alumno->id)->where('periodo_lectivo_id', $periodo->id)->first()
            : null;

        if (! $periodo || ! $becaExistente) {
            $this->confirmandoRevocarBeca = false;

            return;
        }

        $this->authorize('delete', $becaExistente);

        if ($this->alcanceRevocarBeca === 'desde_fecha') {
            $this->validate(
                ['fechaDesdeRevocarBeca' => ['required', 'date']],
                attributes: ['fechaDesdeRevocarBeca' => 'fecha']
            );
        }

        $becaExistente->delete();

        $recalculador->recalcularPorBeca(
            $this->alumno,
            $periodo,
            $this->alcanceRevocarBeca === 'desde_fecha' ? $this->fechaDesdeRevocarBeca : null
        );

        $this->confirmandoRevocarBeca = false;
        $this->reset(['alcanceRevocarBeca', 'fechaDesdeRevocarBeca']);
        session()->flash('mensaje', 'Beca revocada y cuotas pendientes actualizadas.');
    }

    /**
     * El checkbox vuelve a tildarse -- si el admin canceló, la beca sigue
     * vigente, así que la UI no debe quedar mostrando "destildado".
     */
    public function cancelarRevocarBeca(): void
    {
        $this->confirmandoRevocarBeca = false;
        $this->becado = true;
        $this->reset(['alcanceRevocarBeca', 'fechaDesdeRevocarBeca']);
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
            'boletinPendiente' => $this->alumno ? $this->boletinPendienteDeOtroPeriodo($periodoActivo) : null,
            'beca' => $this->becaDelPeriodoActivo(),
        ]);
    }
}
