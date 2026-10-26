<?php

namespace App\Services;

use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Resultado del cálculo de una propuesta. Todos los montos en centavos.
 */
final readonly class Proposal
{
    /**
     * @param  list<array{key: string, name: string, impl_usd: int, month_usd: int}>  $lines  Sitio base primero, después los widgets.
     */
    public function __construct(
        public array $lines,
        public int $implUsd,
        public int $monthUsd,
        public int $fxRate10k,
        public int $implArs,
        public int $monthArs,
        public int $depositArs,
        public int $balanceArs,
        public ?CarbonImmutable $firstBilledMonth = null,
        public int $depositPercent = 50,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $cents = fn (int $c) => round($c / 100, 2);

        return [
            'lines' => array_map(fn ($l) => [
                'key' => $l['key'],
                'name' => $l['name'],
                'impl_usd' => $cents($l['impl_usd']),
                'month_usd' => $cents($l['month_usd']),
            ], $this->lines),
            'usd' => ['impl' => $cents($this->implUsd), 'month' => $cents($this->monthUsd)],
            'fx_rate' => $this->fxRate10k / 10000,
            'ars' => [
                'impl' => $cents($this->implArs),
                'month' => $cents($this->monthArs),
                'deposit' => $cents($this->depositArs),
                'balance' => $cents($this->balanceArs),
            ],
            'first_billed_month' => $this->firstBilledMonth?->format('Y-m'),
        ];
    }

    /** Texto listo para mandar al cliente, igual al del prototipo. */
    public function text(string $clientName): string
    {
        $widgets = array_column(array_slice($this->lines, 1), 'name');
        $billing = $this->firstBilledMonth
            ? 'El abono se cobra desde '.$this->firstBilledMonth->locale('es')->translatedFormat('F \d\e Y').', a mes vencido.'
            : 'El abono se cobra desde el mes siguiente a la publicación, a mes vencido; los días del mes de publicación quedan bonificados.';

        return "Presupuesto web para {$clientName}\n\n"
            .'Incluye: sitio base'.($widgets ? ', '.implode(', ', $widgets) : '').".\n\n"
            .'Implementación: '.Money::ars($this->implArs)."\n"
            ."- Anticipo del {$this->depositPercent}%: ".Money::ars($this->depositArs)."\n"
            .'- Saldo del '.(100 - $this->depositPercent).'% al publicar: '.Money::ars($this->balanceArs)."\n"
            .'Abono mensual: '.Money::ars($this->monthArs)." (hosting, dominio, correo, seguridad, mantenimiento y soporte)\n"
            .$billing."\n\n"
            .'Referencia: implementación '.Money::usd($this->implUsd).', abono '.Money::usd($this->monthUsd)
            .', cotización '.Money::ars(intdiv($this->fxRate10k, 100)).' por USD.';
    }
}
