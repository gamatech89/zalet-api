<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Enums\FilterableEntity;
use App\Enums\FilterableFieldType;
use App\Support\JsonMapping\FilterableField;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(EventType::USER_FOLLOWED)]
class UserFollowedPayload extends EventPayload
{
    public function __construct(
        #[JsonField(name: 'followed_id')]
        #[FilterableField(key: 'followed_user', type: FilterableFieldType::ENTITY, entity: FilterableEntity::USERS)]
        public string $followedId,
    ) {}
}
