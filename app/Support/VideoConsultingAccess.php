<?php

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

    public static function enabledFor(?User $reader): bool
    {
        if (! $reader || ! $reader->isReader()) {
            return false;
        }

        $override = $reader->readerProfile?->video_consulting_enabled;

        if ($override !== null) {
            return (bool) $override;
        }

        return self::enabledGlobally();
    }
}
