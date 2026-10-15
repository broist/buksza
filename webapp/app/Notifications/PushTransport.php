<?php

namespace App\Notifications;

use App\Models\PushDevice;

interface PushTransport
{
    public function send(PushDevice $device, array $payload): array;
}
