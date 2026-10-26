<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_register_creates_unverified_prospect_and_sends_verification(): void
    {
        Notification::fake();

        $this->post('/registro', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'ana@example.com')->sole();
        $this->assertSame(User::ROLE_PROSPECT, $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);

        // Sin verificar no puede mandar pedidos.
        $this->get('/pedido')->assertRedirect(route('verification.notice'));
    }

    public function test_register_works_even_if_mail_fails(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->post('/registro', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->post('/verificar-email/reenviar')->assertSessionHasErrors('review');
        $this->post('/salir');
        $this->post('/recuperar', ['email' => 'ana@example.com'])->assertSessionHasErrors('email');
    }

    public function test_register_cannot_choose_role(): void
    {
        $this->post('/registro', [
            'name' => 'Pícaro',
            'email' => 'picaro@example.com',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
            'role' => 'agency',
        ]);

        $this->assertSame(User::ROLE_PROSPECT, User::where('email', 'picaro@example.com')->sole()->role);
    }

    public function test_verification_link_verifies_and_goes_to_request(): void
    {
        $user = $this->user(User::ROLE_PROSPECT, ['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)->assertRedirect(route('request.create'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_redirects_each_role_home(): void
    {
        foreach ([User::ROLE_PROSPECT => 'request.create', User::ROLE_CLIENT => 'client.home', User::ROLE_AGENCY => 'agency.home'] as $role => $route) {
            $user = $this->user($role);
            $this->post('/ingresar', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route($route));
            $this->post('/salir');
        }
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $user = $this->user(User::ROLE_CLIENT);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/ingresar', ['email' => $user->email, 'password' => 'mal'])->assertSessionHasErrors('email');
        }
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Demasiados intentos.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_roles_cannot_enter_each_others_screens(): void
    {
        $prospect = $this->user(User::ROLE_PROSPECT);
        $client = $this->user(User::ROLE_CLIENT);

        $this->actingAs($prospect)->get('/agencia')->assertRedirect(route('request.create'));
        $this->actingAs($prospect)->get('/mi-panel')->assertRedirect(route('request.create'));
        $this->actingAs($client)->get('/agencia')->assertRedirect(route('client.home'));
        $this->actingAs($client)->get('/pedido')->assertRedirect(route('client.home'));
        $this->actingAs($client)->post('/agencia/pedidos/1/aprobar')->assertRedirect(route('client.home'));
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->user(User::ROLE_CLIENT);

        $this->post('/recuperar', ['email' => $user->email])->assertSessionHas('status');
        $this->post('/recuperar', ['email' => 'nadie@example.com'])->assertSessionHas('status'); // misma respuesta

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/restablecer', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nueva-clave-1',
            'password_confirmation' => 'nueva-clave-1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nueva-clave-1', $user->fresh()->password));
    }

    private function fakeGoogle(string $id, string $email, bool $verified = true): void
    {
        $google = (new GoogleUser)->map(['id' => $id, 'name' => 'Ana Google', 'email' => $email])
            ->setRaw(['email_verified' => $verified]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_creates_verified_prospect(): void
    {
        $this->fakeGoogle('g-1', 'Ana@Gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('request.create'));

        $user = User::where('email', 'ana@gmail.com')->sole();
        $this->assertSame('g-1', $user->google_id);
        $this->assertSame(User::ROLE_PROSPECT, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
    }

    public function test_google_links_existing_agency_account(): void
    {
        $agency = $this->user(User::ROLE_AGENCY, ['email' => 'contacto@misioweb.com']);
        $this->fakeGoogle('g-2', 'contacto@misioweb.com');

        $this->get('/auth/google/callback')->assertRedirect(route('agency.home'));

        $this->assertSame('g-2', $agency->fresh()->google_id);
        $this->assertSame(User::ROLE_AGENCY, $agency->fresh()->role);
        $this->assertSame(1, User::count());
    }

    public function test_google_rejects_unverified_email(): void
    {
        $this->fakeGoogle('g-3', 'raro@example.com', verified: false);

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }
}
