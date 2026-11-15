<?php

// v1.2 — 2026-11-15 | enabledFor() now also admits admin/editor: they're opt-in only (the
//                      tri-state override must be explicitly true — no sitewide default-on,
//                      since the sitewide switch is about readers). Add parseOverrideInput(),
//                      shared by ReaderProfileController and EditorProfileController instead
//                      of each keeping its own copy of the 'default'|'enabled'|'disabled' parse.
// v1.1 — 2026-11-08 | enabledFor(): the per-reader flag is now a tri-state override, not an
//                      AND'd second switch — null means "follow the sitewide setting", true/
//                      false means "always on/off for this reader regardless of the sitewide
//                      setting". So a reader explicitly re-enabled overrides a global disable.
// v1.0 — 2026-11-07 | Video consulting Phase 1 — single source of truth for "is video
//                      consulting actually on for this reader right now". Used by the nav,
//                      the reader self-serve controllers, and the booking API so toggling
//                      either switch hides/blocks everywhere consistently.

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

class VideoConsultingAccess
{
    public static function enabledGlobally(): bool
    {
        return (bool) Setting::getValue('video_consulting_enabled', true);
    }

    public static function enabledFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $override = $user->readerProfile?->video_consulting_enabled;

        if ($user->isReader()) {
            return $override !== null ? (bool) $override : self::enabledGlobally();
        }

        // Admin/editor are opt-in only — there's no sitewide default for them to fall back
        // to, so an unset override means "not offering video consulting."
        if ($user->isAdminOrEditor()) {
            return (bool) $override;
        }

        return false;
    }

    /** 'default'|'enabled'|'disabled' select -> null|true|false for ReaderProfile::video_consulting_enabled. */
    public static function parseOverrideInput(?string $value): ?bool
    {
        return match ($value) {
            'enabled'  => true,
            'disabled' => false,
            default    => null,
        };
    }
}
