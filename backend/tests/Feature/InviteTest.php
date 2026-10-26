<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ClientInvited;
use Database\Seeders\SankalpaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SankalpaDemoSeeder::class);
        $this->project = Project::sole();
        $this->agency = User::factory()->create();
        $this->agency->forceFill(['role' => User::ROLE_AGENCY])->save();
    }

    private function invite(array $data)
    {
        return $this->actingAs($this->agency)->post("/agencia/proyectos/{$this->project->id}/invitar", $data);
    }

    public function test_invited_client_gets_account_and_mail_and_can_set_password(): void
    {
        Notification::fake();

        $this->invite(['name' => 'Laura', 'email' => 'Laura@Sankalpa.com'])
            ->assertSessionHas('status', 'Invitación enviada a laura@sankalpa.com.');

        $user = User::where('email', 'laura@sankalpa.com')->sole();
        $this->assertSame(User::ROLE_CLIENT, $user->role);
        $this->assertSame($this->project->client_id, $user->client_id);

        $url = null;
        Notification::assertSentTo($user, ClientInvited::class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

        // Elige su contraseña con el enlace y entra a su panel.
        preg_match('#/restablecer/([^?]+)#', $url, $m);
        $this->post('/salir');
        $this->post('/restablecer', [
            'token' => $m[1], 'email' => 'laura@sankalpa.com',
            'password' => 'clave-nueva-1', 'password_confirmation' => 'clave-nueva-1',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('clave-nueva-1', $user->fresh()->password));
        $this->post('/ingresar', ['email' => 'laura@sankalpa.com', 'password' => 'clave-nueva-1'])
            ->assertRedirect(route('client.home'));
        $this->get('/mi-panel')->assertSee('Secciones de la portada');
    }

    public function test_existing_prospect_without_client_is_linked(): void
    {
        Notification::fake();
        $prospect = User::factory()->create(['email' => 'ya@registrado.com']);
        $prospect->forceFill(['role' => User::ROLE_PROSPECT])->save();

        $this->invite(['name' => 'Ya', 'email' => 'ya@registrado.com'])->assertSessionHas('status');

        $this->assertSame(User::ROLE_CLIENT, $prospect->fresh()->role);
        $this->assertSame($this->project->client_id, $prospect->fresh()->client_id);
        $this->assertSame(2, User::count());
    }

    public function test_cannot_invite_agency_or_someone_from_another_client(): void
    {
        $this->invite(['name' => 'Yo', 'email' => $this->agency->email])->assertSessionHasErrors('invite');

        $other = User::factory()->create(['email' => 'otro@cliente.com']);
        $other->forceFill(['role' => User::ROLE_CLIENT, 'client_id' => Client::create(['name' => 'Otro'])->id])->save();
        $this->invite(['name' => 'Otro', 'email' => 'otro@cliente.com'])->assertSessionHasErrors('invite');
        $this->assertSame(User::ROLE_AGENCY, $this->agency->fresh()->role);
    }

    public function test_if_mail_fails_the_link_is_shown_to_copy(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->invite(['name' => 'Laura', 'email' => 'laura@sankalpa.com'])
            ->assertSessionHasErrors('review')
            ->assertSessionHas('invite_url', fn ($url) => str_contains($url, '/restablecer/'));
        $this->assertSame(User::ROLE_CLIENT, User::where('email', 'laura@sankalpa.com')->value('role'));
    }

    public function test_only_agency_can_invite(): void
    {
        $client = User::factory()->create();
        $client->forceFill(['role' => User::ROLE_CLIENT, 'client_id' => $this->project->client_id])->save();

        $this->actingAs($client)->post("/agencia/proyectos/{$this->project->id}/invitar", ['name' => 'X', 'email' => 'x@x.com'])
            ->assertRedirect(route('client.home'));
        $this->assertNull(User::where('email', 'x@x.com')->first());
    }
}
