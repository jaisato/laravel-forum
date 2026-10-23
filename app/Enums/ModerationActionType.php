<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every staff (or system) action recorded in the moderation log.
 */
enum ModerationActionType: string
{
    case ThreadPinned = 'thread_pinned';
    case ThreadUnpinned = 'thread_unpinned';
    case ThreadLocked = 'thread_locked';
    case ThreadUnlocked = 'thread_unlocked';
    case ThreadMoved = 'thread_moved';
    case ThreadEdited = 'thread_edited';
    case ThreadDeleted = 'thread_deleted';
    case ThreadRestored = 'thread_restored';
    case PostEdited = 'post_edited';
    case PostHidden = 'post_hidden';
    case PostApproved = 'post_approved';
    case PostDeleted = 'post_deleted';
    case PostRestored = 'post_restored';
    case ReportResolved = 'report_resolved';
    case ReportDismissed = 'report_dismissed';
    case UserBanned = 'user_banned';
    case UserUnbanned = 'user_unbanned';
    case UserRoleChanged = 'user_role_changed';

    public function label(): string
    {
        return match ($this) {
            self::ThreadPinned => __('Thread pinned'),
            self::ThreadUnpinned => __('Thread unpinned'),
            self::ThreadLocked => __('Thread locked'),
            self::ThreadUnlocked => __('Thread unlocked'),
            self::ThreadMoved => __('Thread moved'),
            self::ThreadEdited => __('Thread edited'),
            self::ThreadDeleted => __('Thread deleted'),
            self::ThreadRestored => __('Thread restored'),
            self::PostEdited => __('Post edited'),
            self::PostHidden => __('Post hidden'),
            self::PostApproved => __('Post approved'),
            self::PostDeleted => __('Post deleted'),
            self::PostRestored => __('Post restored'),
            self::ReportResolved => __('Report resolved'),
            self::ReportDismissed => __('Report dismissed'),
            self::UserBanned => __('User banned'),
            self::UserUnbanned => __('User unbanned'),
            self::UserRoleChanged => __('Role changed'),
        };
    }
}
