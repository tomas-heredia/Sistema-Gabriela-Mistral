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
 */
#[Layout('layouts.app')]
class Cargar extends Component
{
    public BoletinTrimestre $boletinTrimestre;

    public array $datos = [];

    public function mount(BoletinTrimestre $boletinTrimestre): void
    {
        $this->authorize('update', $boletinTrimestre->boletin);

        $this->boletinTrimestre = $boletinTrimestre;

        $existentes = $boletinTrimestre->datos ?? [];

        foreach ($this->seccionesVisibles() as $entry) {
            $id = $entry['seccion']['id'];
            $this->datos[$id] = $existentes[$id] ?? $this->defaultParaSeccion($entry['seccion'], $entry['columnas']);
        }
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

    public function confirmarYEnviar(): void
    {
        try {
            $this->boletinTrimestre->confirmarYEnviar();
            session()->flash('mensaje', 'Trimestre confirmado: el PDF se está generando y se enviará por mail a los tutores.');
            $this->redirectRoute('boletines.index', navigate: true);
        } catch (TransicionDeEstadoInvalidaException $e) {
            $this->dispatch('close-modal', name: 'confirmar-envio');
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
        return view('livewire.boletines.trimestres.cargar', [
            'secciones' => $this->seccionesVisibles(),
            'soloLectura' => $this->boletinTrimestre->estado === EstadoTrimestre::Enviado,
        ]);
    }
}
