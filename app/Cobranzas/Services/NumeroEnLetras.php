<?php

namespace App\Cobranzas\Services;

/**
 * Convierte un monto a su expresión en letras ("SON: ...") sin depender de
 * `NumberFormatter::SPELLOUT`: el ICU que trae Alpine (la imagen que corre
 * en el VPS) no incluye las reglas de deletreo para español, así que
 * silenciosamente caía al inglés ("SIXTY-THREE THOUSAND PESOS") -- nunca
 * pasó en Windows porque ahí el ICU que viene con PHP sí las trae
 * completas. No hay forma de saber de antemano qué locales de SPELLOUT
 * trae el ICU de un servidor dado, así que mejor no depender de eso.
 */
class NumeroEnLetras
{
    private const UNIDADES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];

    private const DIEZ_A_DIECINUEVE = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];

    private const VEINTIS = ['veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];

    private const DECENAS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];

    private const CENTENAS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    public static function pesos(int $centavos): string
    {
        $pesos = intdiv(abs($centavos), 100);
        $centavosRestantes = abs($centavos) % 100;

        $texto = mb_strtoupper(self::apocopar(self::convertir($pesos))).' '.($pesos === 1 ? 'PESO' : 'PESOS');

        if ($centavosRestantes > 0) {
            $texto .= ' CON '.mb_strtoupper(self::apocopar(self::convertir($centavosRestantes))).' '.($centavosRestantes === 1 ? 'CENTAVO' : 'CENTAVOS');
        }

        return $centavos < 0 ? "MENOS {$texto}" : $texto;
    }

    private static function convertir(int $numero): string
    {
        if ($numero === 0) {
            return 'cero';
        }

        if ($numero < 10) {
            return self::UNIDADES[$numero];
        }

        if ($numero < 20) {
            return self::DIEZ_A_DIECINUEVE[$numero - 10];
        }

        if ($numero < 30) {
            return self::VEINTIS[$numero - 20];
        }

        if ($numero < 100) {
            $decena = intdiv($numero, 10);
            $unidad = $numero % 10;

            return $unidad === 0 ? self::DECENAS[$decena] : self::DECENAS[$decena].' y '.self::UNIDADES[$unidad];
        }

        if ($numero === 100) {
            return 'cien';
        }

        if ($numero < 1000) {
            $centena = intdiv($numero, 100);
            $resto = $numero % 100;

            return $resto === 0 ? self::CENTENAS[$centena] : self::CENTENAS[$centena].' '.self::convertir($resto);
        }

        if ($numero < 2000) {
            $resto = $numero % 1000;

            return $resto === 0 ? 'mil' : 'mil '.self::convertir($resto);
        }

        if ($numero < 1_000_000) {
            $miles = intdiv($numero, 1000);
            $resto = $numero % 1000;
            $textoMiles = self::apocopar(self::convertir($miles)).' mil';

            return $resto === 0 ? $textoMiles : $textoMiles.' '.self::convertir($resto);
        }

        $millones = intdiv($numero, 1_000_000);
        $resto = $numero % 1_000_000;
        $textoMillones = $millones === 1 ? 'un millón' : self::apocopar(self::convertir($millones)).' millones';

        return $resto === 0 ? $textoMillones : $textoMillones.' '.self::convertir($resto);
    }

    /**
     * "uno" se acorta a "un" delante de otra palabra (mil, millones, el
     * sustantivo final) -- "veintiuno" es el único caso que además cambia
     * de grafía a "veintiún" (con tilde), por eso ese patrón va primero.
     */
    private static function apocopar(string $texto): string
    {
        return preg_replace(['/veintiuno$/', '/uno$/'], ['veintiún', 'un'], $texto);
    }
}
