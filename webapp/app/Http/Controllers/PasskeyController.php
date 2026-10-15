<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\WebAuthn;

class PasskeyController extends Controller
{
    private function webauthn(): WebAuthn
    {
        return new WebAuthn('Buksza', parse_url(config('app.url'), PHP_URL_HOST), ['none'], true);
    }

    private function handle(int $id): string
    {
        return hash_hmac('sha256', 'buksza-user-'.$id, config('app.key'), true);
    }

    private function binary(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false || strlen($decoded) > 65536) {
            $this->invalid();
        }

        return $decoded;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['passkey' => 'A passkey ellenőrzése nem sikerült vagy lejárt. Próbáld újra, vagy lépj be jelszóval.']);
    }

    private function challenge(Request $request, string $kind, string $clientData): string
    {
        $state = $request->session()->pull('webauthn');
        if (! $state || $state['kind'] !== $kind || $state['expires'] < now()->timestamp || ($kind === 'register' && $state['user_id'] !== $request->user()->id)) {
            $this->invalid();
        }
        if (! Cache::add('webauthn-used:'.hash('sha256', $state['challenge']), true, 180)) {
            $this->invalid();
        }
        $client = json_decode($clientData, true);
        if (($client['origin'] ?? null) !== rtrim(config('app.url'), '/') || ($client['crossOrigin'] ?? false) !== false) {
            $this->invalid();
        }

        return base64_decode($state['challenge']);
    }

    public function options(Request $request, string $kind)
    {
        abort_unless(in_array($kind, ['register', 'login']), 404);
        if ($kind === 'register') {
            abort_unless($request->user(), 401);
            $request->validate(['password' => 'required|current_password']);
        }
        $web = $this->webauthn();
        if ($kind === 'register') {
            $ids = DB::table('passkeys')->where('user_id', $request->user()->id)->pluck('credential_id')->map(fn ($id) => base64_decode($id))->all();
            $args = $web->getCreateArgs($this->handle($request->user()->id), $request->user()->email, $request->user()->name, 120, true, true, null, $ids);
        } else {
            $args = $web->getGetArgs([], 120, true, true, true, true, true, true);
        }
        $request->session()->put('webauthn', ['kind' => $kind, 'user_id' => $request->user()?->id, 'challenge' => base64_encode($web->getChallenge()->getBinaryString()), 'expires' => now()->timestamp + 120]);

        return response()->json($args)->header('Cache-Control', 'private, no-store');
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'rawId' => 'required|string|max:4096', 'clientDataJSON' => 'required|string|max:16000', 'attestationObject' => 'required|string|max:90000']);
        $client = $this->binary($data['clientDataJSON']);
        $challenge = $this->challenge($request, 'register', $client);
        try {
            $result = $this->webauthn()->processCreate($client, $this->binary($data['attestationObject']), $challenge, true);
            if (! hash_equals($result->credentialId, $this->binary($data['rawId']))) {
                $this->invalid();
            }
            DB::table('passkeys')->insert(['user_id' => $request->user()->id, 'name' => $data['name'], 'credential_id' => base64_encode($result->credentialId), 'credential_hash' => hash('sha256', $result->credentialId), 'public_key' => $result->credentialPublicKey, 'sign_count' => $result->signatureCounter ?? 0, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable) {
            $this->invalid();
        }

        return response()->json(['ok' => true]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['rawId' => 'required|string|max:4096', 'clientDataJSON' => 'required|string|max:16000', 'authenticatorData' => 'required|string|max:90000', 'signature' => 'required|string|max:12000', 'userHandle' => 'required|string|max:4096']);
        $client = $this->binary($data['clientDataJSON']);
        $challenge = $this->challenge($request, 'login', $client);
        $credential = DB::table('passkeys')->where('credential_hash', hash('sha256', $this->binary($data['rawId'])))->first();
        if (! $credential || ! hash_equals($this->handle($credential->user_id), $this->binary($data['userHandle']))) {
            $this->invalid();
        }
        try {
            $web = $this->webauthn();
            $web->processGet($client, $this->binary($data['authenticatorData']), $this->binary($data['signature']), $credential->public_key, $challenge, $credential->sign_count, true);
            DB::table('passkeys')->where('id', $credential->id)->update(['sign_count' => $web->getSignatureCounter() ?? $credential->sign_count, 'last_used_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable) {
            $this->invalid();
        }
        Auth::login(User::findOrFail($credential->user_id), true);
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }

    public function delete(Request $request, int $id)
    {
        $request->validate(['password' => 'required|current_password']);
        $count = DB::table('passkeys')->where('id', $id)->where('user_id', $request->user()->id)->delete();
        abort_unless($count, 404);

        return back()->with('message', 'Passkey törölve. Jelszóval továbbra is beléphetsz.');
    }
}
