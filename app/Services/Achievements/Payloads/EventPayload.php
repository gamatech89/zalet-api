<?php

namespace App\Services\Achievements\Payloads;

use App\Enums\EventType;
use App\Support\JsonMapping\FilterableField;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonType;
use ReflectionClass;

#[JsonType(field: 'type', subtypes: [
    GiftSentPayload::class,
    MessageSentPayload::class,
    UserFollowedPayload::class,
    FollowerGainedPayload::class,
    StreamStartedPayload::class,
    DailyLoginPayload::class,
    MediaPostedPayload::class,
])]
abstract class EventPayload
{
    public static function fieldsFor(EventType $eventType): array
    {
        $classMap = [
            EventType::GIFT_SENT->value => GiftSentPayload::class,
            EventType::MESSAGE_SENT->value => MessageSentPayload::class,
            EventType::USER_FOLLOWED->value => UserFollowedPayload::class,
            EventType::FOLLOWER_GAINED->value => FollowerGainedPayload::class,
            EventType::STREAM_STARTED->value => StreamStartedPayload::class,
            EventType::DAILY_LOGIN->value => DailyLoginPayload::class,
            EventType::MEDIA_POSTED->value => MediaPostedPayload::class,
        ];

        $class = $classMap[$eventType->value] ?? null;

        if (! $class) {
            return [];
        }

        return self::describeFields($class);
    }

    private static function describeFields(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            $filterAttr = $property->getAttributes(FilterableField::class)[0] ?? null;

            if (! $filterAttr) {
                continue;
            }

            $filter = $filterAttr->newInstance();
            $jsonAttribute = $property->getAttributes(JsonField::class)[0] ?? null;
            $jsonField = $jsonAttribute?->newInstance();

            $values = null;
            if ($filter->values && enum_exists($filter->values)) {
                $values = array_map(fn ($case) => $case->value, $filter->values::cases());
            }

            $fields[] = [
                'field' => $jsonField?->name ?? $property->getName(),
                'key' => $filter->key,
                'type' => $filter->type->value,
                'entity' => $filter->entity?->value,
                'values' => $values,
            ];
        }

        return $fields;
    }
}
