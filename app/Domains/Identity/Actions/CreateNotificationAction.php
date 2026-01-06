<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Events\NotificationSent;
use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Create a notification for a user.
 */
final class CreateNotificationAction
{
    /**
     * Create a new notification.
     *
     * @param array<string, mixed>|null $data
     */
    public function execute(
        User $user,
        NotificationType $type,
        string $title,
        ?string $body = null,
        ?User $actor = null,
        ?Model $notifiable = null,
        ?array $data = null,
        bool $broadcast = true,
    ): Notification {
        $notification = Notification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'actor_id' => $actor?->id,
            'notifiable_type' => $notifiable ? $notifiable::class : null,
            'notifiable_id' => $notifiable?->getKey(),
            'data' => $data,
            'read_at' => null,
        ]);

        // Broadcast the notification in real-time
        if ($broadcast) {
            NotificationSent::dispatch($notification);
        }

        return $notification;
    }

    /**
     * Create a follow request notification.
     */
    public function followRequest(User $user, User $requester): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::FOLLOW_REQUEST,
            title: 'New Follow Request',
            body: ($requester->profile->display_name ?? $requester->email) . ' wants to follow you',
            actor: $requester,
        );
    }

    /**
     * Create a follow accepted notification.
     */
    public function followAccepted(User $user, User $followedUser): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::FOLLOW_ACCEPTED,
            title: 'Follow Request Accepted',
            body: ($followedUser->profile->display_name ?? $followedUser->email) . ' accepted your follow request',
            actor: $followedUser,
        );
    }

    /**
     * Create a new follower notification (for public profiles).
     */
    public function newFollower(User $user, User $follower): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::NEW_FOLLOWER,
            title: 'New Follower',
            body: ($follower->profile->display_name ?? $follower->email) . ' started following you',
            actor: $follower,
        );
    }

    /**
     * Create a gift received notification.
     */
    public function giftReceived(User $user, User $sender, string $giftName, int $creditsValue): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::GIFT_RECEIVED,
            title: 'Gift Received!',
            body: ($sender->profile->display_name ?? $sender->email) . " sent you a {$giftName}",
            actor: $sender,
            data: [
                'gift_name' => $giftName,
                'credits_value' => $creditsValue,
            ],
        );
    }

    /**
     * Create a duel gift received notification.
     */
    public function duelGiftReceived(
        User $user,
        User $sender,
        string $giftType,
        int $credits,
        Model $liveSession,
    ): Notification {
        return $this->execute(
            user: $user,
            type: NotificationType::DUEL_GIFT_RECEIVED,
            title: 'Duel Gift!',
            body: ($sender->profile->display_name ?? $sender->email) . " sent a {$giftType} during your duel",
            actor: $sender,
            notifiable: $liveSession,
            data: [
                'gift_type' => $giftType,
                'credits' => $credits,
            ],
        );
    }

    /**
     * Create a duel invite notification.
     */
    public function duelInvite(User $user, User $host, ?Model $liveSession = null): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::DUEL_INVITE,
            title: 'Duel Invitation',
            body: ($host->profile->display_name ?? $host->email) . ' invited you to a duel',
            actor: $host,
            notifiable: $liveSession,
        );
    }

    /**
     * Create a duel started notification.
     */
    public function duelStarted(User $user, User $opponent, Model $liveSession): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::DUEL_STARTED,
            title: 'Duel Started!',
            body: 'Your duel with ' . ($opponent->profile->display_name ?? $opponent->email) . ' has begun',
            actor: $opponent,
            notifiable: $liveSession,
        );
    }

    /**
     * Create a purchase completed notification.
     */
    public function purchaseCompleted(User $user, int $credits, string $packageName): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::PURCHASE_COMPLETED,
            title: 'Purchase Successful',
            body: "You've received {$credits} credits from your {$packageName} purchase",
            data: [
                'credits' => $credits,
                'package_name' => $packageName,
            ],
        );
    }

    /**
     * Create a purchase failed notification.
     */
    public function purchaseFailed(User $user, string $reason): Notification
    {
        return $this->execute(
            user: $user,
            type: NotificationType::PURCHASE_FAILED,
            title: 'Purchase Failed',
            body: $reason,
        );
    }

    /**
     * Create a system announcement notification for all users.
     *
     * @param array<User>|\Illuminate\Support\Collection<int, User> $users
     * @return array<Notification>
     */
    public function systemAnnouncement(iterable $users, string $title, string $body): array
    {
        $notifications = [];

        foreach ($users as $user) {
            $notifications[] = $this->execute(
                user: $user,
                type: NotificationType::SYSTEM_ANNOUNCEMENT,
                title: $title,
                body: $body,
            );
        }

        return $notifications;
    }
}
