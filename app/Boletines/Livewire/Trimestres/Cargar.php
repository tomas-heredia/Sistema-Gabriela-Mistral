<?php

namespace App\Boletines\Livewire\Trimestres;

use App\Boletines\Exceptions\TransicionDeEstadoInvalidaException;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formulario genérico: no hay un componente por tipo de sección. Se recorre
 * `estructura_campos.secciones` de la plantilla y se infiere qué dibujar
 * según la forma de cada sección (grilla filas×columnas, o campo único),
 * igual que decidimos para el PDF (ver resources/views/boletines/partials/
 * valor-generico.blade.php) en vez de imitar el diseño de cada escaneo.
 *
 * Cada columna de una grilla (y cada sección de campo único) tiene un
 * "momento" (trimestre_1/2/3/4). Al cargar el trimestre N solo se piden las
 * columnas/secciones de ese momento — el resto ni se muestra.
 *
 * Excepciones puntuales y documentadas a ese render genérico (mismo
 * criterio que ya tienen campo_calculado/tabla_materias_libre): las
 * secciones de filas libres recuerdan su valor de un trimestre anterior
 * (ver mount()/valorPrevioDe()), y "Calificación Final" de Espacios
 * Curriculares (secundaria, 3er trimestre) se recalcula sola mientras se
 * completan ICAIP/trimestre_3, sin pisar una que el usuario ya corrigió a
 * mano (ver updated()/recalcularCalificacionFinal()).
 */
#[Layout('layouts.app')]
class Cargar extends Component
{
    public BoletinTrimestre $boletinTrimestre;

    public array $datos = [];

    /**
     * Por índice de fila de "espacios_curriculares": si el usuario ya tocó
     * a mano la celda "calificacion_final" de esa fila, updated() deja de
     * recalcularla sola (ver recalcularCalificacionFinal()). Nunca se
     * persiste en `datos` -- es solo estado del componente, para no filtrar
     * una clave de control al render genérico del PDF.
     */
    public array $calificacionFinalManual = [];

    public function mount(BoletinTrimestre $boletinTrimestre): void
    {
        $this->authorize('update', $boletinTrimestre->boletin);

        $this->boletinTrimestre = $boletinTrimestre;

        $existentes = $boletinTrimestre->datos ?? [];

        foreach ($this->seccionesVisibles() as $entry) {
            $seccion = $entry['seccion'];

            if (($seccion['tipo'] ?? null) === 'campo_calculado') {
                continue;
            }

            $id = $seccion['id'];

            // Espacios Pendientes de Acreditación (y cualquier sección de
            // filas libres futura): lo cargado en un trimestre anterior se
            // mantiene como punto de partida editable en los siguientes, en
            // vez de arrancar vacío -- salvo que este trimestre ya tenga su
            // propio dato guardado (aunque sea un array vacío a propósito,
            // por eso isset() y no un ?? directo acá).
            if (($seccion['filas_libres'] ?? false) && ! isset($existentes[$id])) {
                $this->datos[$id] = $this->valorPrevioDe($id) ?? $this->defaultParaSeccion($seccion, $entry['columnas']);
            } else {
                $this->datos[$id] = $existentes[$id] ?? $this->defaultParaSeccion($seccion, $entry['columnas']);
            }

            if ($id === 'espacios_curriculares') {
                foreach ($this->datos[$id] as $indice => $fila) {
                    if (($fila['calificacion_final'] ?? '') !== '') {
                        $this->calificacionFinalManual[$indice] = true;
                    }
                }
            }
        }
    }

    /**
     * Valor más reciente de una sección de filas libres en un trimestre
     * anterior del mismo boletín (ej. Espacios Pendientes de Acreditación
     * cargado en el 1er trimestre, de punto de partida para el 2do/3ro).
     */
    private function valorPrevioDe(string $seccionId): ?array
    {
        return $this->boletinTrimestre->boletin->trimestres()
            ->where('trimestre', '<', $this->boletinTrimestre->trimestre)
            ->orderByDesc('trimestre')
            ->get()
            ->map(fn ($t) => $t->datos[$seccionId] ?? null)
            ->first(fn ($valor) => $valor !== null);
    }

    /**
     * Livewire 3: se dispara para toda propiedad sincronizada, incluyendo
     * rutas anidadas como "datos.espacios_curriculares.3.icaip_febrero".
     * Solo nos importan las celdas de Espacios Curriculares que alimentan
     * "Calificación Final" (secundaria, 3er trimestre) -- en primaria esas
     * columnas ni existen en la plantilla, así que esto no hace nada ahí.
     */
    public function updated(string $name): void
    {
        if (! preg_match('/^datos\.espacios_curriculares\.(\d+)\.([a-z0-9_]+)$/', $name, $m)) {
            return;
        }

        $indice = (int) $m[1];
        $columna = $m[2];

        if ($columna === 'calificacion_final') {
            $this->calificacionFinalManual[$indice] = true;

            return;
        }

        if (in_array($columna, ['trimestre_3', 'icaip_diciembre', 'icaip_febrero'], true)
            && ! ($this->calificacionFinalManual[$indice] ?? false)) {
            $this->recalcularCalificacionFinal($indice);
        }
    }

    /**
     * "Calificación Final" sugerida mientras se completan ICAIP o la nota
     * del 3er trimestre, sin pisar una que el usuario ya tocó a mano (ver
     * calificacionFinalManual). Prioridad: ICAIP Febrero, si no ICAIP
     * Diciembre, si no el promedio anual de la materia.
     */
    private function recalcularCalificacionFinal(int $indice): void
    {
        $fila = $this->datos['espacios_curriculares'][$indice] ?? null;

        if (! $fila) {
            return;
        }

        $final = match (true) {
            is_numeric($fila['icaip_febrero'] ?? null) => (float) $fila['icaip_febrero'],
            is_numeric($fila['icaip_diciembre'] ?? null) => (float) $fila['icaip_diciembre'],
            default => $this->promedioAnual($fila),
        };

        $this->datos['espacios_curriculares'][$indice]['calificacion_final'] = $final !== null ? (string) $final : '';
    }

    /**
     * Promedio de trimestre_1/2/3 de una materia: trimestre_3 ya está en la
     * propia fila que se está cargando; trimestre_1/2 viven en los
     * BoletinTrimestre de esos trimestres, cada uno con su propia fila por
     * materia (mismo criterio que GestorDeRecuperaciones::notasPorMateria()).
     */
    private function promedioAnual(array $fila): ?float
    {
        $nombre = $fila['nombre'] ?? null;

        $notas = $this->boletinTrimestre->boletin->trimestres()
            ->whereIn('trimestre', [1, 2])
            ->get()
            ->map(function ($trimestre) use ($nombre) {
                $materia = collect($trimestre->datos['espacios_curriculares'] ?? [])->firstWhere('nombre', $nombre);

                return $materia["trimestre_{$trimestre->trimestre}"] ?? null;
            })
            ->push($fila['trimestre_3'] ?? null)
            ->filter(fn ($valor) => is_numeric($valor))
            ->map(fn ($valor) => (float) $valor);

        return $notas->isNotEmpty() ? round($notas->avg(), 1) : null;
    }

    /**
     * Solo estas celdas de Espacios Curriculares (secundaria, 3er
     * trimestre) necesitan ida y vuelta al servidor en cada tecla, para que
     * Calificación Final se recalcule sola mientras se completan (ver
     * updated()) -- el resto del formulario sigue diferido como siempre.
     */
    public function disparaRecalculo(array $seccion, array $columna): bool
    {
        return ($seccion['id'] ?? null) === 'espacios_curriculares'
            && in_array($columna['id'] ?? null, ['trimestre_3', 'icaip_diciembre', 'icaip_febrero'], true);
    }

    /**
     * Secciones de la plantilla que aplican al trimestre que se está
     * cargando, ya filtradas a las columnas de ese momento. Una sección sin
     * ninguna columna visible para este trimestre no se devuelve.
     */
    public function seccionesVisibles(): array
    {
        $momentoActual = "trimestre_{$this->boletinTrimestre->trimestre}";
        $secciones = $this->boletinTrimestre->boletin->plantilla->estructura_campos['secciones'] ?? [];
        $visibles = [];

        foreach ($secciones as $seccion) {
            if ($this->esGrilla($seccion)) {
                $columnas = $this->columnasVisibles($seccion, $momentoActual);

                if (empty($columnas)) {
                    continue;
                }

                $visibles[] = ['seccion' => $seccion, 'columnas' => $columnas];
            } else {
                if (($seccion['momento'] ?? null) !== $momentoActual) {
                    continue;
                }

                $visibles[] = ['seccion' => $seccion, 'columnas' => []];
            }
        }

        return $visibles;
    }

    public function agregarFila(string $seccionId): void
    {
        $entry = collect($this->seccionesVisibles())->firstWhere('seccion.id', $seccionId);

        if (! $entry) {
            return;
        }

        $columnaIds = collect($entry['columnas'])->pluck('id')->all();
        $this->datos[$seccionId][] = ['nombre' => ''] + array_fill_keys($columnaIds, '');
    }

    public function quitarFila(string $seccionId, int $indice): void
    {
        unset($this->datos[$seccionId][$indice]);
        $this->datos[$seccionId] = array_values($this->datos[$seccionId]);
    }

    public function guardarBorrador(): void
    {
        $this->normalizarDatos();

        try {
            $this->boletinTrimestre->cargar($this->datos, auth()->user());
            session()->flash('mensaje', 'Borrador guardado correctamente.');
        } catch (TransicionDeEstadoInvalidaException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function guardarCorreccion(): void
    {
        $this->authorize('corregir', $this->boletinTrimestre->boletin);

        $this->normalizarDatos();

        try {
            $this->boletinTrimestre->corregir($this->datos, auth()->user());
            session()->flash('mensaje', 'Corrección guardada. No se les avisó todavía a los tutores -- usá "Reenviar corrección" para mandarles la nueva versión.');
        } catch (TransicionDeEstadoInvalidaException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reenviarCorreccion(): void
    {
        $this->authorize('corregir', $this->boletinTrimestre->boletin);

        try {
            $this->boletinTrimestre->reenviarCorreccion(auth()->user());
            session()->flash('mensaje', 'La corrección se está reenviando por mail a los tutores.');
        } catch (TransicionDeEstadoInvalidaException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function confirmarYEnviar(): void
    {
        try {
            $this->boletinTrimestre->confirmarYEnviar(auth()->user());
            session()->flash('mensaje', 'Trimestre confirmado: el PDF se está generando y se enviará por mail a los tutores.');
            $this->redirectRoute('boletines.index', navigate: true);
        } catch (TransicionDeEstadoInvalidaException $e) {
            // Sin nombre: ver el comentario en Pagos\Listado::prepararAnulacion()
            // sobre por qué un parámetro con nombre acá hace que x-modal nunca
            // reciba el string que espera y el modal se quede abierto.
            $this->dispatch('close-modal', 'confirmar-envio');
            session()->flash('error', $e->getMessage());
        }
    }

    public function tipoInput(array $seccion, array $columna): string
    {
        $tipo = $columna['tipo'] ?? $seccion['escala']['tipo'] ?? 'texto';

        return match ($tipo) {
            'numerica', 'numero' => 'number',
            'fecha' => 'date',
            default => (isset($seccion['escala']['opciones']) && ! isset($columna['tipo'])) ? 'select' : 'text',
        };
    }

    /**
     * Límite [min, max] de un campo numérico. La asistencia son conteos de
     * días — no tienen techo, solo no pueden ser negativos. Todo lo demás
     * numérico (notas de materias, talleres, espacios pendientes, promedio)
     * es una calificación en la escala 0–10 del colegio.
     */
    public function limitesNumericos(array $seccion, array $columna = []): array
    {
        if (($seccion['tipo'] ?? null) === 'tabla_metricas') {
            return [0, null];
        }

        if (($seccion['tipo'] ?? null) === 'campo_numero') {
            return [0, 10];
        }

        $tipo = $columna['tipo'] ?? $seccion['escala']['tipo'] ?? null;

        if (in_array($tipo, ['numerica', 'numero'], true)) {
            return [0, 10];
        }

        return [null, null];
    }

    /**
     * Muestra junto a cada valor de una escala de opciones (S/AV/PV/N, etc.)
     * su significado completo, tomado de la "leyenda" de la plantilla — así
     * el cobrador no tiene que recordar qué significa cada sigla.
     */
    public function etiquetaOpcion(array $seccion, string $opcion): string
    {
        $opciones = $seccion['escala']['opciones'] ?? [];
        $leyenda = $seccion['escala']['leyenda'] ?? null;

        if (! $leyenda) {
            return $opcion;
        }

        $significados = array_map('trim', explode('/', $leyenda));
        $indice = array_search($opcion, $opciones, true);

        if ($indice === false || ! isset($significados[$indice])) {
            return $opcion;
        }

        return "{$opcion} — {$significados[$indice]}";
    }

    /**
     * "Promedio de Materias" (Libreta Final): nunca se guarda en `datos` --
     * se recalcula siempre a partir de la columna "nota_final" que el
     * cobrador/director está corrigiendo, así nunca queda desactualizado.
     */
    public function promedioCalculado(array $seccion): ?float
    {
        $filas = $this->datos[$seccion['fuente']['seccion']] ?? [];

        $numeros = collect($filas)
            ->pluck($seccion['fuente']['columna'])
            ->filter(fn ($valor) => is_numeric($valor))
            ->map(fn ($valor) => (float) $valor);

        return $numeros->isNotEmpty() ? round($numeros->avg(), 1) : null;
    }

    private function esGrilla(array $seccion): bool
    {
        return str_starts_with($seccion['tipo'] ?? '', 'tabla_');
    }

    /**
     * Una columna sin "momento" declarado no está atada a un trimestre en
     * particular y se muestra siempre (ej. "Espacios Pendientes de
     * Acreditación": se puede acreditar en cualquier trimestre, no solo al
     * cierre del año).
     */
    private function columnasVisibles(array $seccion, string $momentoActual): array
    {
        return collect($seccion['columnas'] ?? [])
            ->filter(fn ($columna) => ! isset($columna['momento']) || $columna['momento'] === $momentoActual)
            ->values()
            ->all();
    }

    /**
     * Ajusta cualquier valor numérico fuera de rango al límite más cercano
     * antes de guardar (ej. una nota de 12 pasa a 10, una asistencia de -3
     * pasa a 0), en vez de rechazar el borrador.
     */
    private function normalizarDatos(): void
    {
        foreach ($this->seccionesVisibles() as $entry) {
            $seccion = $entry['seccion'];
            $seccionId = $seccion['id'];

            if (($seccion['tipo'] ?? null) === 'campo_calculado') {
                continue;
            }

            if ($this->esGrilla($seccion)) {
                foreach ($this->datos[$seccionId] ?? [] as $indice => $fila) {
                    foreach ($entry['columnas'] as $columna) {
                        $columnaId = $columna['id'];

                        if (! array_key_exists($columnaId, $fila)) {
                            continue;
                        }

                        [$min, $max] = $this->limitesNumericos($seccion, $columna);
                        $this->datos[$seccionId][$indice][$columnaId] = $this->clamparNumero($fila[$columnaId], $min, $max);
                    }
                }
            } else {
                [$min, $max] = $this->limitesNumericos($seccion);
                $this->datos[$seccionId] = $this->clamparNumero($this->datos[$seccionId] ?? null, $min, $max);
            }
        }
    }

    private function clamparNumero(mixed $valor, ?float $min, ?float $max): mixed
    {
        if (($min === null && $max === null) || $valor === '' || $valor === null || ! is_numeric($valor)) {
            return $valor;
        }

        $numero = (float) $valor;

        if ($min !== null) {
            $numero = max($min, $numero);
        }

        if ($max !== null) {
            $numero = min($max, $numero);
        }

        return fmod($numero, 1.0) === 0.0 ? (string) (int) $numero : (string) $numero;
    }

    /**
     * Aplana las filas fijas de una sección, incluyendo subfilas (ej.
     * "Lenguajes Artísticos" → Música/Artes Visuales/Danza/Teatro pasan a
     * ser filas propias, no un nivel anidado extra).
     */
    private function filasPlanas(array $seccion): array
    {
        $filas = [];

        foreach ($seccion['filas'] ?? [] as $fila) {
            if (isset($fila['subfilas'])) {
                array_push($filas, ...$fila['subfilas']);
            } else {
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    private function defaultParaSeccion(array $seccion, array $columnas): array|string
    {
        if (! $this->esGrilla($seccion)) {
            return '';
        }

        if ($seccion['filas_libres'] ?? false) {
            return [];
        }

        $columnaIds = collect($columnas)->pluck('id')->all();

        return collect($this->filasPlanas($seccion))
            ->map(fn ($fila) => ['nombre' => $fila['nombre']] + array_fill_keys($columnaIds, ''))
            ->values()
            ->all();
    }

    public function render()
    {
        $trimestreEnviado = $this->boletinTrimestre->estado === EstadoTrimestre::Enviado;
        $puedeCorregir = $trimestreEnviado && auth()->user()->can('corregir', $this->boletinTrimestre->boletin);

        return view('livewire.boletines.trimestres.cargar', [
            'secciones' => $this->seccionesVisibles(),
            'trimestreEnviado' => $trimestreEnviado,
            'puedeCorregir' => $puedeCorregir,
            'soloLectura' => $trimestreEnviado && ! $puedeCorregir,
            'requiereReenvio' => $this->boletinTrimestre->requiere_reenvio,
        ]);
    }
}
