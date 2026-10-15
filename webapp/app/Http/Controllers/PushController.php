<?php

namespace App\Http\Controllers;

use App\Models\PushDevice;
use App\Notifications\PushTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PushController extends Controller
{
    public function settings(Request $request)
    {
        $data = $request->validate(['daily_enabled' => 'required|boolean', 'anna_enabled' => 'required|boolean', 'daily_time' => 'required|date_format:H:i', 'anna_time' => 'required|date_format:H:i']);
        DB::table('reminder_settings')->updateOrInsert(['user_id' => $request->user()->id], [...$data, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('message', 'Emlékeztetők elmentve. Az időpontok Budapest szerint értendők.');
    }

    public function register(Request $request)
    {
        abort_unless(str_starts_with(config('app.url'), 'https://') && DB::table('push_credentials')->where('id', 1)->exists(), 422, 'Előbb HTTPS és értesítési kulcs szükséges.');
        $data = $request->validate(['device_id' => 'required|uuid', 'name' => 'required|string|max:80', 'subscription' => 'required|array', 'subscription.endpoint' => 'required|string|max:2000', 'subscription.keys.p256dh' => ['required', 'regex:/^[A-Za-z0-9_-]{87}=?$/'], 'subscription.keys.auth' => ['required', 'regex:/^[A-Za-z0-9_-]{22}={0,2}$/']]);
        $endpoint = $data['subscription']['endpoint'];
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');
        $allowed = $host === 'fcm.googleapis.com' || $host === 'updates.push.services.mozilla.com' || str_ends_with($host, '.push.apple.com') || $host === 'web.push.apple.com' || str_ends_with($host, '.notify.windows.com');
        if (! $allowed || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw ValidationException::withMessages(['subscription' => 'Ismeretlen értesítési szolgáltató.']);
        }
        $hash = hash('sha256', $endpoint);
        $existing = PushDevice::where('device_id', $data['device_id'])->orWhere('endpoint_hash', $hash)->first();
        abort_if($existing && $existing->user_id !== $request->user()->id, 403);
        $device = $existing ?? new PushDevice;
        $device->fill(['device_id' => $data['device_id'], 'user_id' => $request->user()->id, 'name' => $data['name'], 'endpoint_hash' => $hash, 'subscription' => ['endpoint' => $endpoint, 'keys' => $data['subscription']['keys']], 'enabled' => true])->save();
        DB::table('reminder_settings')->insertOrIgnore(['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('message', 'A telefon regisztrálva. Küldj próbaértesítést a fogadás ellenőrzéséhez.');
    }

    public function disable(Request $request, PushDevice $device)
    {
        abort_unless($device->user_id === $request->user()->id, 403);
        $device->update(['enabled' => false]);

        return back()->with('message', 'Ezen az eszközön leállítottuk a jelzéseket.');
    }

    public function trial(Request $request, PushDevice $device, PushTransport $transport)
    {
        abort_unless($device->user_id === $request->user()->id, 403);
        abort_unless($device->enabled, 422);
        try {
            $result = $transport->send($device, ['title' => 'Próbaértesítés', 'body' => 'Az értesítések működnek ezen a telefonon.', 'tag' => 'buksza-trial', 'url' => '/esti-rogzites']);
        } catch (\Throwable) {
            $result = ['success' => false, 'expired' => false];
        }
        $device->update(['last_status' => $result['success'] ? 'sent' : 'failed', ...($result['success'] ? ['last_success_at' => now()] : []), ...($result['expired'] ? ['enabled' => false] : [])]);

        return back()->with('message', $result['success'] ? 'A szolgáltató átvette a próbaértesítést. Ellenőrizd a telefonon a megjelenést.' : 'A küldés nem sikerült. Ellenőrizd a szerver és a telefon értesítési beállításait.');
    }
}
