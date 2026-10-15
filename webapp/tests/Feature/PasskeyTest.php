<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_options_need_password_and_require_resident_verified_credentials(): void
    {
        $user = User::factory()->create(['password' => 'passkey-test-password']);
        $this->postJson('/passkey/register/opciok')->assertUnauthorized();
        $this->actingAs($user)->postJson('/passkey/register/opciok', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->postJson('/passkey/register/opciok', ['password' => 'passkey-test-password'])->assertOk()->assertJsonPath('publicKey.authenticatorSelection.userVerification', 'required')->assertJsonPath('publicKey.authenticatorSelection.residentKey', 'required')->assertJsonPath('publicKey.rp.id', parse_url(config('app.url'), PHP_URL_HOST));
        $this->assertDatabaseCount('passkeys', 0);
    }

    public function test_invalid_origin_expired_or_replayed_challenges_cannot_log_in(): void
    {
        $this->postJson('/passkey/login/opciok')->assertOk()->assertJsonPath('publicKey.userVerification', 'required');
        $body = ['rawId' => base64_encode('id'), 'clientDataJSON' => base64_encode(json_encode(['origin' => 'https://attacker.test'])), 'authenticatorData' => 'AA==', 'signature' => 'AA==', 'userHandle' => 'AA=='];
        $this->postJson('/passkey/login', $body)->assertJsonValidationErrors('passkey');
        $this->assertGuest();
        $body['clientDataJSON'] = base64_encode(json_encode(['origin' => rtrim(config('app.url'), '/')]));
        $this->postJson('/passkey/login', $body)->assertJsonValidationErrors('passkey');
        $this->postJson('/passkey/login/opciok')->assertOk();
        $this->travel(3)->minutes();
        $this->postJson('/passkey/login', $body)->assertJsonValidationErrors('passkey');
        $this->assertGuest();
    }

    public function test_passkey_delete_checks_ownership_and_current_password(): void
    {
        $user = User::factory()->create(['password' => 'passkey-test-password']);
        $other = User::factory()->create();
        $id = DB::table('passkeys')->insertGetId(['user_id' => $other->id, 'name' => 'Másik kulcs', 'credential_id' => 'AA==', 'credential_hash' => hash('sha256', 'other'), 'public_key' => 'public', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->delete("/passkey/$id", ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->delete("/passkey/$id", ['password' => 'passkey-test-password'])->assertNotFound();
        $this->assertDatabaseCount('passkeys', 1);
    }
}
