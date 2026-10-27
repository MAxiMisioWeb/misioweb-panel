<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Widget;
use App\Services\ProposalCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SankalpaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    private function widgets(string ...$keys): array
    {
        return Widget::whereIn('key', $keys)->orderBy('sort')->get()->all();
    }

    /** Presupuesto real de Sankalpa (CLAUDE.md): USD 299 / 44,99 con cotización 1530. */
    public function test_sankalpa_budget(): void
    {
        $p = app(ProposalCalculator::class)->calculate(
            $this->widgets('blog', 'calendario', 'reels', 'eventos', 'tienda'),
            '1530',
        );

        $this->assertSame(29900, $p->implUsd);
        $this->assertSame(4499, $p->monthUsd);
        $this->assertSame(45747000, $p->implArs);   // $457.470
        $this->assertSame(6883470, $p->monthArs);   // $68.834,70
        $this->assertSame(22873500, $p->depositArs);
        $this->assertSame(22873500, $p->balanceArs);
        $this->assertCount(6, $p->lines);
        $this->assertSame('base', $p->lines[0]['key']);
    }

    public function test_sankalpa_project_text(): void
    {
        $this->seed(SankalpaDemoSeeder::class);
        $project = Project::firstOrFail();

        $text = app(ProposalCalculator::class)->forProject($project)->text('Sankalpa');

        $this->assertStringContainsString('Incluye: sitio base, Blog, Calendario de actividades, Reels de Instagram (ReelsLoop), Eventos Misioweb, Tienda con Mercado Pago.', $text);
        $this->assertStringContainsString('Implementación: $457.470', $text);
        $this->assertStringContainsString('- Anticipo del 50%: $228.735', $text);
        $this->assertStringContainsString('- Saldo del 50% al publicar: $228.735', $text);
        $this->assertStringContainsString('Abono mensual: $68.834,70', $text);
        $this->assertStringContainsString('Referencia: implementación USD 299, abono USD 44,99, cotización $1.530 por USD.', $text);
    }

    public function test_base_only_and_unselected_widgets_do_not_count(): void
    {
        $p = app(ProposalCalculator::class)->calculate([], '1000');

        $this->assertSame(9900, $p->implUsd);
        $this->assertSame(1499, $p->monthUsd);
        $this->assertSame(1499000, $p->monthArs); // 14,99 × 1000 = $14.990
    }

    public function test_deposit_and_balance_always_add_up(): void
    {
        // 99 + 10 = 109 USD × 1001,15 = 109.125,35 → anticipo 54.562,68 y saldo 54.562,67
        $p = app(ProposalCalculator::class)->calculate($this->widgets('whatsapp'), '1001.15');

        $this->assertSame(10912535, $p->implArs);
        $this->assertSame(5456268, $p->depositArs);
        $this->assertSame($p->implArs, $p->depositArs + $p->balanceArs);
    }

    public function test_billing_starts_the_month_after_publishing(): void
    {
        $first = fn (string $at) => ProposalCalculator::firstBilledMonth(CarbonImmutable::parse($at))->format('Y-m-d');

        $this->assertSame('2026-11-01', $first('2026-10-15 12:00:00'));
        $this->assertSame('2027-01-01', $first('2026-12-31 23:30:00'));
        $this->assertSame('2026-03-01', $first('2026-02-01 00:00:00'));
        // 01/11 02:00 UTC todavía es 31/10 en Argentina: se cobra desde noviembre.
        $this->assertSame('2026-11-01', $first('2026-11-01 02:00:00 UTC'));
    }

    public function test_text_mentions_first_billed_month_when_published(): void
    {
        $p = app(ProposalCalculator::class)->calculate([], '1530', CarbonImmutable::parse('2026-10-15'));

        $this->assertStringContainsString('El abono se cobra desde noviembre de 2026, a mes vencido.', $p->text('Prueba'));
        $this->assertSame('2026-11', $p->toArray()['first_billed_month']);
    }
}
