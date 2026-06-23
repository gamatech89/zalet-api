<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Enums\FilterableFieldType;
use App\Enums\MediaProvider;
use App\Enums\MediaType;
use App\Support\JsonMapping\FilterableField;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(EventType::MEDIA_POSTED)]
class MediaPostedPayload extends EventPayload
{
    public function __construct(
        #[JsonField(name: 'media_type')]
        #[FilterableField(key: 'media_type', type: FilterableFieldType::STRING, values: MediaType::class)]
        public MediaType $mediaType,

        #[JsonField(name: 'provider')]
        #[FilterableField(key: 'provider', type: FilterableFieldType::STRING, values: MediaProvider::class)]
        public MediaProvider $provider,
    ) {}
}
