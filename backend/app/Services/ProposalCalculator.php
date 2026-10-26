<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Widget;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Calcula la propuesta según docs/spec.md:
 * - implementación = base + suma de widgets elegidos (USD); abono igual.
 * - pesos = USD × cotización, redondeado a 2 decimales.
 * - anticipo 50% y saldo 50%.
 * - el abono se computa desde el mes calendario siguiente a la publicación.
 */
class ProposalCalculator
{
    public function forProject(Project $project): Proposal
    {
        return $this->calculate($project->widgets()->get()->all(), (string) $project->fx_rate, $project->published_at);
    }

    /**
     * @param  list<Widget>  $widgets
     */
    public function calculate(array $widgets, string $fxRate, ?DateTimeInterface $publishedAt = null): Proposal
    {
        $lines = [[
            'key' => 'base',
            'name' => 'Sitio base',
            'impl_usd' => Money::toScaled(config('panel.base.price_usd_impl'), 2),
            'month_usd' => Money::toScaled(config('panel.base.price_usd_month'), 2),
        ]];
        foreach ($widgets as $w) {
            $lines[] = [
                'key' => $w->key,
                'name' => $w->name,
                'impl_usd' => Money::toScaled($w->price_usd_impl, 2),
                'month_usd' => Money::toScaled($w->price_usd_month, 2),
            ];
        }

        $implUsd = array_sum(array_column($lines, 'impl_usd'));
        $monthUsd = array_sum(array_column($lines, 'month_usd'));
        $rate = Money::toScaled($fxRate, 4);
        $implArs = Money::convert($implUsd, $rate);
        $depositPercent = (int) config('panel.deposit_percent');
        $deposit = Money::percent($implArs, $depositPercent);

        return new Proposal(
            lines: $lines,
            implUsd: $implUsd,
            monthUsd: $monthUsd,
            fxRate10k: $rate,
            implArs: $implArs,
            monthArs: Money::convert($monthUsd, $rate),
            depositArs: $deposit,
            balanceArs: $implArs - $deposit,
            firstBilledMonth: $publishedAt ? self::firstBilledMonth($publishedAt) : null,
            depositPercent: $depositPercent,
        );
    }

    /**
     * Primer mes que se cobra: el mes calendario siguiente a la publicación (en la zona horaria
     * de la app). Los días del mes de publicación quedan bonificados.
     */
    public static function firstBilledMonth(DateTimeInterface $publishedAt): CarbonImmutable
    {
        return CarbonImmutable::instance($publishedAt)
            ->setTimezone(config('app.timezone'))
            ->startOfMonth()
            ->addMonthNoOverflow();
    }
}
