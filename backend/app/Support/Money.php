<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Montos en centavos (enteros) para no arrastrar errores de punto flotante.
 */
final class Money
{
    /**
     * Convierte un decimal en texto ("44.99", "1530.0000") a entero escalado.
     * Ej: toScaled('44.99', 2) = 4499; toScaled('1530', 4) = 15300000.
     */
    public static function toScaled(string|int|float $value, int $scale): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $m)) {
            throw new InvalidArgumentException("Monto inválido: {$value}");
        }
        $frac = $m[3] ?? '';
        if (strlen($frac) > $scale) {
            // Redondeo al último decimal que se guarda (mitad hacia arriba).
            $up = (int) $frac[$scale] >= 5;
            $frac = substr($frac, 0, $scale);
            $n = (int) ($m[2].$frac) + ($up ? 1 : 0);
        } else {
            $n = (int) ($m[2].str_pad($frac, $scale, '0'));
        }

        return $m[1] === '-' ? -$n : $n;
    }

    /**
     * Multiplica centavos por una cotización con 4 decimales y redondea a centavos.
     */
    public static function convert(int $cents, int $rate10k): int
    {
        return intdiv($cents * $rate10k + 5000, 10000);
    }

    /** Porcentaje de un monto en centavos, redondeado al centavo. */
    public static function percent(int $cents, int $percent): int
    {
        return intdiv($cents * $percent + 50, 100);
    }

    /** "$457.470" o "$68.834,70": decimales solo si hay centavos, como en el prototipo. */
    public static function ars(int $cents): string
    {
        return '$'.self::format($cents);
    }

    public static function usd(int $cents): string
    {
        return 'USD '.self::format($cents);
    }

    private static function format(int $cents): string
    {
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return number_format($cents / 100, $decimals, ',', '.');
    }
}
