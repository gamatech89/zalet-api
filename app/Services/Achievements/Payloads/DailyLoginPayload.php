<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(EventType::DAILY_LOGIN)]
class DailyLoginPayload extends EventPayload
{
}
