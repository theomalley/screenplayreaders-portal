<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — single source of truth for "is video
//                      consulting actually on for this reader right now", combining the
//                      sitewide kill switch (Setting::video_consulting_enabled) with the
//                      per-reader one (ReaderProfile::video_consulting_enabled). Both must
//                      be on. Used by the nav, the reader self-serve controllers, and the
//                      booking API so disabling either hides/blocks everywhere consistently.

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

        if (! self::enabledGlobally()) {
            return false;
        }

        return (bool) ($reader->readerProfile?->video_consulting_enabled ?? true);
    }
}
