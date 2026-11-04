<?php

namespace App\Boletines\Services;

use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\TipoTrimestre;

/**
 * Encadena automáticamente lo que viene después de un trimestre regular:
 * si al cerrar el 3er trimestre el alumno tiene materias desaprobadas,
 * genera la recuperación de diciembre (PAeIP en primaria, ICAP en
 * secundaria) con solo esas materias; si en diciembre alguna sigue sin
 * llegar a 6, genera la de febrero con el subconjunto que falta; y en
 * cualquier desenlace (aprobó todo en el 3er trimestre, o terminó su paso
 * por diciembre/febrero, apruebe o no) genera la Libreta Final con todas
 * las materias y la nota final de cada una precargada.
 *
 * Se llama desde GenerarYEnviarBoletinPdf::handle(), después de marcar el
 * trimestre como enviado -- nunca desde Cargar/Livewire, así que crea las
 * filas siguientes directo con BoletinTrimestre::create() (sin pasar por
 * cargar(), que necesita un User autenticado) igual que ya hace
 * CreadorDeBoletines con los trimestres 1/2/3.
 */
class GestorDeRecuperaciones
{
    private const NOTA_APROBACION = 6;

    public function procesarEnvio(BoletinTrimestre $trimestre): void
    {
        match ($trimestre->tipo()) {
            TipoTrimestre::Regular => $this->procesarRegular($trimestre),
            TipoTrimestre::RecuperacionDiciembre => $this->procesarRecuperacionDiciembre($trimestre),
            TipoTrimestre::RecuperacionFebrero => $this->procesarRecuperacionFebrero($trimestre),
            TipoTrimestre::LibretaFinal => null,
        };
    }

    private function procesarRegular(BoletinTrimestre $trimestre): void
    {
        if ($trimestre->trimestre !== 3) {
            return;
        }

        $boletin = $trimestre->boletin;
        $desaprobadas = $this->materiasDesaprobadas($boletin);

        if (empty($desaprobadas)) {
            $this->generarLibretaFinal($boletin);

            return;
        }

        $this->generarRecuperacion($boletin, BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE, $desaprobadas, 'nota_diciembre');
    }

    private function procesarRecuperacionDiciembre(BoletinTrimestre $trimestre): void
    {
        $boletin = $trimestre->boletin;
        $aunDesaprobadas = $this->materiasAunDesaprobadas($trimestre, 'nota_diciembre');

        if (empty($aunDesaprobadas)) {
            $this->generarLibretaFinal($boletin);

            return;
        }

        $this->generarRecuperacion($boletin, BoletinTrimestre::TRIMESTRE_RECUPERACION_FEBRERO, $aunDesaprobadas, 'nota_febrero');
    }

    private function procesarRecuperacionFebrero(BoletinTrimestre $trimestre): void
    {
        // Febrero es la última instancia: se genera la Libreta Final apruebe
        // o no, a diferencia de diciembre que solo la genera si ya aprobó todo.
        $this->generarLibretaFinal($trimestre->boletin);
    }

    /**
     * Materias desaprobadas al cierre del 3er trimestre: primaria con 1 o
     * más de sus 3 notas trimestrales por debajo de la nota de aprobación;
     * secundaria con 2 o más, o específicamente la del 3er trimestre.
     */
    private function materiasDesaprobadas(Boletin $boletin): array
    {
        $notas = $this->notasPorMateria($boletin, [1, 2, 3]);
        $esPrimaria = $boletin->alumno->nivel === Nivel::Primario;

        return collect($notas)
            ->filter(function (array $trimestres) use ($esPrimaria) {
                $desaprobados = collect($trimestres)->filter(fn (?float $nota) => $nota !== null && $nota < self::NOTA_APROBACION);

                if ($esPrimaria) {
                    return $desaprobados->isNotEmpty();
                }

                $nota3 = $trimestres['trimestre_3'] ?? null;
                $fallaTrimestre3 = $nota3 !== null && $nota3 < self::NOTA_APROBACION;

                return $desaprobados->count() >= 2 || $fallaTrimestre3;
            })
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Materias que, dentro de una recuperación (diciembre o febrero), no
     * llegaron a la nota de aprobación con la nota recién enviada -- una
     * nota en blanco o no numérica también cuenta como no aprobada.
     */
    private function materiasAunDesaprobadas(BoletinTrimestre $trimestre, string $columna): array
    {
        return collect($trimestre->datos['espacios_curriculares'] ?? [])
            ->filter(function (array $fila) use ($columna) {
                $nota = $fila[$columna] ?? null;

                return ! is_numeric($nota) || (float) $nota < self::NOTA_APROBACION;
            })
            ->pluck('nombre')
            ->values()
            ->all();
    }

    /**
     * Mapa nombre de materia → ['trimestre_1' => nota|null, ...], leyendo
     * la propia columna de cada trimestre regular desde su propia fila
     * (cada trimestre solo guarda la columna que le tocó cargar).
     */
    private function notasPorMateria(Boletin $boletin, array $numerosTrimestre): array
    {
        $resultado = [];

        foreach ($numerosTrimestre as $numero) {
            $trimestre = $boletin->trimestres()->where('trimestre', $numero)->first();
            $columnaId = "trimestre_{$numero}";

            foreach (($trimestre?->datos['espacios_curriculares'] ?? []) as $fila) {
                $nota = $fila[$columnaId] ?? null;
                $resultado[$fila['nombre']][$columnaId] = is_numeric($nota) ? (float) $nota : null;
            }
        }

        return $resultado;
    }

    /**
     * Mapa nombre de materia → nota, para una recuperación ya enviada
     * (diciembre o febrero). Solo incluye materias con una nota numérica
     * cargada.
     */
    private function notasDeRecuperacion(?BoletinTrimestre $trimestre, string $columna): array
    {
        if (! $trimestre) {
            return [];
        }

        return collect($trimestre->datos['espacios_curriculares'] ?? [])
            ->filter(fn (array $fila) => is_numeric($fila[$columna] ?? null))
            ->mapWithKeys(fn (array $fila) => [$fila['nombre'] => (float) $fila[$columna]])
            ->all();
    }

    private function generarRecuperacion(Boletin $boletin, int $numeroTrimestre, array $materias, string $columna): void
    {
        if ($boletin->trimestres()->where('trimestre', $numeroTrimestre)->exists()) {
            return;
        }

        BoletinTrimestre::create([
            'boletin_id' => $boletin->id,
            'trimestre' => $numeroTrimestre,
            'datos' => [
                'espacios_curriculares' => collect($materias)
                    ->map(fn (string $nombre) => ['nombre' => $nombre, $columna => ''])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * La nota final de cada materia: si pasó por recuperación, la de la
     * última instancia (febrero si existe, si no diciembre); si no
     * necesitó recuperación, el promedio de trimestre_1/2/3.
     */
    private function generarLibretaFinal(Boletin $boletin): void
    {
        if ($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL)->exists()) {
            return;
        }

        $diciembre = $boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE)->first();
        $febrero = $boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_FEBRERO)->first();

        $notasRecuperacion = $this->notasDeRecuperacion($febrero, 'nota_febrero')
            + $this->notasDeRecuperacion($diciembre, 'nota_diciembre');

        $promedios = $this->notasPorMateria($boletin, [1, 2, 3]);

        $filas = collect(array_keys($promedios))
            ->map(function (string $nombre) use ($notasRecuperacion, $promedios) {
                if (isset($notasRecuperacion[$nombre])) {
                    $notaFinal = $notasRecuperacion[$nombre];
                } else {
                    $valores = collect($promedios[$nombre])->filter(fn (?float $nota) => $nota !== null);
                    $notaFinal = $valores->isNotEmpty() ? round($valores->avg(), 1) : null;
                }

                return ['nombre' => $nombre, 'nota_final' => $notaFinal !== null ? (string) $notaFinal : ''];
            })
            ->values()
            ->all();

        BoletinTrimestre::create([
            'boletin_id' => $boletin->id,
            'trimestre' => BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL,
            'datos' => ['espacios_curriculares' => $filas],
        ]);
    }
}
