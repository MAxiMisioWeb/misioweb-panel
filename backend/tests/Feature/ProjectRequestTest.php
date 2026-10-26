<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Notifications\NewProjectRequest;
use App\Notifications\ProjectApproved;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    private function user(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_prospect_sees_widgets_with_prices(): void
    {
        $this->actingAs($this->user(User::ROLE_PROSPECT))
            ->get('/pedido')
            ->assertOk()
            ->assertSee('Tienda con Mercado Pago')
            ->assertSee('suma USD 70 y USD 12 por mes')
            ->assertSee('USD 99'); // solo el sitio base
    }

    public function test_full_flow_request_approve_and_client_panel(): void
    {
        Notification::fake();
        $agency = $this->user(User::ROLE_AGENCY);
        $prospect = $this->user(User::ROLE_PROSPECT, ['name' => 'Ana']);

        // 1. El prospecto manda el pedido.
        $this->actingAs($prospect)->post('/pedido', [
            'business_name' => 'Sankalpa',
            'widgets' => ['blog', 'calendario', 'reels', 'eventos', 'tienda'],
            'note' => 'Queremos arrancar en diciembre',
        ])->assertRedirect(route('request.create'))->assertSessionHas('status');

        $project = Project::sole();
        $this->assertSame('requested', $project->status);
        $this->assertSame('Sankalpa', $project->client->name);
        $this->assertSame(5, $project->widgets()->count());
        Notification::assertSentTo($agency, NewProjectRequest::class);

        // No puede mandar otro mientras espera.
        $this->actingAs($prospect)->get('/pedido')->assertSee('Esperando revisión');
        $this->actingAs($prospect)->post('/pedido', ['business_name' => 'Otra'])->assertRedirect(route('request.create'));
        $this->assertSame(1, Project::count());

        // 2. La agencia ve el pedido con la inversión y lo aprueba.
        $this->actingAs($agency)->get('/agencia')
            ->assertSee('Sankalpa')
            ->assertSee('USD 299')
            ->assertSee('$457.470')
            ->assertSee('Queremos arrancar en diciembre');
        $this->actingAs($agency)->post("/agencia/pedidos/{$project->id}/aprobar")
            ->assertSessionHas('status', 'Pedido de Sankalpa aprobado. Le mandamos el mail con el acceso.');

        $this->assertSame('in_progress', $project->fresh()->status);
        $this->assertSame($agency->id, $project->fresh()->reviewed_by);
        $this->assertSame(User::ROLE_CLIENT, $prospect->fresh()->role);
        Notification::assertSentTo($prospect, ProjectApproved::class);

        // 3. El cliente entra a su panel.
        $this->actingAs($prospect->fresh())->get('/pedido')->assertRedirect(route('client.home'));
        $this->actingAs($prospect->fresh())->get('/mi-panel')
            ->assertOk()
            ->assertSee('$457.470')
            ->assertSee('$68.834,70');

        // Aprobar de nuevo no hace nada.
        $this->actingAs($agency)->post("/agencia/pedidos/{$project->id}/aprobar")->assertSessionHasErrors('review');
    }

    public function test_reject_keeps_prospect_who_can_send_again(): void
    {
        Notification::fake();
        $agency = $this->user(User::ROLE_AGENCY);
        $prospect = $this->user(User::ROLE_PROSPECT);
        $this->actingAs($prospect)->post('/pedido', ['business_name' => 'Kiosco', 'widgets' => ['whatsapp']]);
        $project = Project::sole();

        $this->actingAs($agency)->post("/agencia/pedidos/{$project->id}/rechazar")->assertSessionHas('status');

        $this->assertSame('rejected', $project->fresh()->status);
        $this->assertSame(User::ROLE_PROSPECT, $prospect->fresh()->role);
        Notification::assertNotSentTo($prospect, ProjectApproved::class);

        $this->actingAs($prospect)->post('/pedido', ['business_name' => 'Kiosco', 'widgets' => ['whatsapp', 'blog']]);
        $this->assertSame(2, Project::count());
        $this->assertSame(1, $prospect->fresh()->client()->count());
    }

    public function test_inactive_or_unknown_widgets_are_rejected(): void
    {
        $this->actingAs($this->user(User::ROLE_PROSPECT))
            ->post('/pedido', ['business_name' => 'X', 'widgets' => ['no-existe']])
            ->assertSessionHasErrors('widgets.0');
        $this->assertSame(0, Project::count());
    }

    public function test_approval_mail_uses_sales_address_when_configured(): void
    {
        config(['panel.sales_from' => ['address' => 'ventas@misioweb.com', 'name' => 'MisioWeb Ventas']]);
        $prospect = $this->user(User::ROLE_PROSPECT);
        $this->actingAs($prospect)->post('/pedido', ['business_name' => 'Kiosco']);

        $mail = (new ProjectApproved(Project::sole()))->toMail($prospect);

        $this->assertSame(['ventas@misioweb.com', 'MisioWeb Ventas'], $mail->from);
        $this->assertSame([['ventas@misioweb.com', 'MisioWeb Ventas']], $mail->replyTo);
        $this->assertSame('Tu panel de MisioWeb está listo', $mail->subject);
    }

    public function test_mail_failure_does_not_undo_approval(): void
    {
        $agency = $this->user(User::ROLE_AGENCY);
        $prospect = $this->user(User::ROLE_PROSPECT);
        Notification::fake();
        $this->actingAs($prospect)->post('/pedido', ['business_name' => 'Kiosco']);
        $project = Project::sole();

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP caído'));

        $this->actingAs($agency)->post("/agencia/pedidos/{$project->id}/aprobar")
            ->assertSessionHasErrors('review');
        $this->assertSame('in_progress', $project->fresh()->status);
        $this->assertSame(User::ROLE_CLIENT, $prospect->fresh()->role);
    }

    public function test_agency_command_creates_account_with_reset_link(): void
    {
        $this->artisan('panel:agencia', ['email' => 'Contacto@MisioWeb.com'])
            ->expectsOutputToContain('contacto@misioweb.com es cuenta de agencia.')
            ->expectsOutputToContain('/restablecer/')
            ->assertSuccessful();

        $user = User::sole();
        $this->assertSame(User::ROLE_AGENCY, $user->role);
        $this->assertNotNull($user->email_verified_at);
    }
}
