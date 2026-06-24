<?php

namespace App\Services\Achievements\Payloads;

use App\Support\JsonMapping\JsonTypeCast;

class EventPayloadCast extends JsonTypeCast
{
    public function __construct()
    {
        parent::__construct(EventPayload::class);
    }
}
