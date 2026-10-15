<?php

namespace App\Notifications;

use App\Models\PushDevice;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;

final class WebPushTransport implements PushTransport
{
    public function __construct(private ?ClientInterface $client = null) {}

    public function send(PushDevice $device, array $payload): array
    {
        $keys = DB::table('push_credentials')->where('id', 1)->first();
        if (! $keys || ! str_starts_with(config('app.url'), 'https://')) {
            return ['success' => false, 'expired' => false];
        }
        $push = new WebPush(['VAPID' => ['subject' => config('app.url'), 'publicKey' => $keys->public_key, 'privateKey' => Crypt::decryptString($keys->private_key)]], ['TTL' => 600, 'urgency' => 'normal'], $this->client ?? new Client(['timeout' => 15, 'allow_redirects' => false]), logger: new NullLogger);
        $report = $push->sendOneNotification(Subscription::create([...$device->subscription, 'contentEncoding' => 'aes128gcm']), json_encode($payload, JSON_UNESCAPED_UNICODE));

        return ['success' => $report->isSuccess(), 'expired' => $report->isSubscriptionExpired()];
    }
}
