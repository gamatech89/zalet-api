<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Enums\FilterableEntity;
use App\Enums\FilterableFieldType;
use App\Support\JsonMapping\FilterableField;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(EventType::GIFT_SENT)]
class GiftSentPayload extends EventPayload
{
    public function __construct(
        #[JsonField(name: 'gift_id')]
        #[FilterableField(key: 'gift', type: FilterableFieldType::ENTITY, entity: FilterableEntity::GIFTS)]
        public string $giftId,

        #[JsonField(name: 'recipient_id')]
        #[FilterableField(key: 'recipient', type: FilterableFieldType::ENTITY, entity: FilterableEntity::USERS)]
        public string $recipientId,

        #[JsonField(name: 'coin_price')]
        #[FilterableField(key: 'coin_price', type: FilterableFieldType::NUMBER)]
        public float $coinPrice,
    ) {}
}
