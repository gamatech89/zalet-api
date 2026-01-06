<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Types of notifications in the system.
 */
enum NotificationType: string
{
    // Social
    case FOLLOW_REQUEST = 'follow_request';
    case FOLLOW_ACCEPTED = 'follow_accepted';
    case NEW_FOLLOWER = 'new_follower';

    // Messaging
    case NEW_MESSAGE = 'new_message';
    case CONVERSATION_STARTED = 'conversation_started';

    // Gifts & Wallet
    case GIFT_RECEIVED = 'gift_received';
    case CREDITS_RECEIVED = 'credits_received';
    case PURCHASE_COMPLETED = 'purchase_completed';
    case PURCHASE_FAILED = 'purchase_failed';

    // Duels
    case DUEL_INVITE = 'duel_invite';
    case DUEL_STARTED = 'duel_started';
    case DUEL_ENDED = 'duel_ended';
    case DUEL_GIFT_RECEIVED = 'duel_gift_received';

    // Content
    case POST_LIKED = 'post_liked';
    case POST_COMMENTED = 'post_commented';
    case POST_SHARED = 'post_shared';

    // System
    case SYSTEM_ANNOUNCEMENT = 'system_announcement';
    case ACCOUNT_VERIFIED = 'account_verified';
    case ROLE_UPGRADED = 'role_upgraded';

    /**
     * Get human-readable label for the notification type.
     */
    public function label(): string
    {
        return match ($this) {
            self::FOLLOW_REQUEST => 'Follow Request',
            self::FOLLOW_ACCEPTED => 'Follow Request Accepted',
            self::NEW_FOLLOWER => 'New Follower',
            self::NEW_MESSAGE => 'New Message',
            self::CONVERSATION_STARTED => 'New Conversation',
            self::GIFT_RECEIVED => 'Gift Received',
            self::CREDITS_RECEIVED => 'Credits Received',
            self::PURCHASE_COMPLETED => 'Purchase Completed',
            self::PURCHASE_FAILED => 'Purchase Failed',
            self::DUEL_INVITE => 'Duel Invitation',
            self::DUEL_STARTED => 'Duel Started',
            self::DUEL_ENDED => 'Duel Ended',
            self::DUEL_GIFT_RECEIVED => 'Duel Gift Received',
            self::POST_LIKED => 'Post Liked',
            self::POST_COMMENTED => 'New Comment',
            self::POST_SHARED => 'Post Shared',
            self::SYSTEM_ANNOUNCEMENT => 'System Announcement',
            self::ACCOUNT_VERIFIED => 'Account Verified',
            self::ROLE_UPGRADED => 'Role Upgraded',
        };
    }

    /**
     * Get the icon name for this notification type (for frontend).
     */
    public function icon(): string
    {
        return match ($this) {
            self::FOLLOW_REQUEST, self::FOLLOW_ACCEPTED, self::NEW_FOLLOWER => 'user-plus',
            self::NEW_MESSAGE, self::CONVERSATION_STARTED => 'message-circle',
            self::GIFT_RECEIVED, self::DUEL_GIFT_RECEIVED => 'gift',
            self::CREDITS_RECEIVED => 'coins',
            self::PURCHASE_COMPLETED => 'check-circle',
            self::PURCHASE_FAILED => 'x-circle',
            self::DUEL_INVITE, self::DUEL_STARTED, self::DUEL_ENDED => 'swords',
            self::POST_LIKED => 'heart',
            self::POST_COMMENTED => 'message-square',
            self::POST_SHARED => 'share',
            self::SYSTEM_ANNOUNCEMENT => 'megaphone',
            self::ACCOUNT_VERIFIED => 'badge-check',
            self::ROLE_UPGRADED => 'award',
        };
    }

    /**
     * Check if this notification type should be grouped.
     */
    public function isGroupable(): bool
    {
        return in_array($this, [
            self::POST_LIKED,
            self::POST_COMMENTED,
            self::NEW_FOLLOWER,
            self::DUEL_GIFT_RECEIVED,
        ], true);
    }

    /**
     * Check if this notification type should trigger a push notification.
     */
    public function shouldPush(): bool
    {
        return match ($this) {
            self::NEW_MESSAGE, self::CONVERSATION_STARTED => false, // Handled by chat badge
            self::SYSTEM_ANNOUNCEMENT => true, // Always push system announcements
            default => true,
        };
    }

    /**
     * Check if this notification type should send an email.
     */
    public function shouldEmail(): bool
    {
        return in_array($this, [
            self::FOLLOW_REQUEST,
            self::GIFT_RECEIVED,
            self::PURCHASE_COMPLETED,
            self::PURCHASE_FAILED,
            self::DUEL_INVITE,
            self::SYSTEM_ANNOUNCEMENT,
        ], true);
    }
}
