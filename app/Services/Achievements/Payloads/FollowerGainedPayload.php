<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Enums\FilterableEntity;
use App\Enums\FilterableFieldType;
use App\Support\JsonMapping\FilterableField;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(EventType::FOLLOWER_GAINED)]
class FollowerGainedPayload extends EventPayload
{
    public function __construct(
        #[JsonField(name: 'follower_id')]
        #[FilterableField(key: 'follower', type: FilterableFieldType::ENTITY, entity: FilterableEntity::USERS)]
        public string $followerId,
    ) {}
}
