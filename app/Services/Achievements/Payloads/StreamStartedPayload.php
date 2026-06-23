<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(EventType::STREAM_STARTED)]
class StreamStartedPayload extends EventPayload
{
}
