<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Enums\FilterableEntity;
use App\Enums\FilterableFieldType;
use App\Support\JsonMapping\FilterableField;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(EventType::MESSAGE_SENT)]
class MessageSentPayload extends EventPayload
{
    public function __construct(
        #[JsonField(name: 'conversation_id')]
        #[FilterableField(key: 'conversation', type: FilterableFieldType::ENTITY, entity: FilterableEntity::CONVERSATIONS)]
        public string $conversationId,
    ) {}
}
