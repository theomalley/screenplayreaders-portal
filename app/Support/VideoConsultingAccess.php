<?php

// v1.8 — 2026-11-19 | Removed readerCouponMatches()/describeReaderCoupon()/applyReaderDiscount()
//                      and the reader discount-code feature entirely — readers will instead note
//                      a sale in their existing note field and lower their own rate.
// v1.6 — 2026-11-18 | eligibleForReaderRequestWaiver(): correction — editors do NOT always
//                      write coverage (only readers do by default). Editor is now opt-in via
//                      video_consulting_reader_request_eligible, same as admin; only a plain
//                      reader is automatically eligible.
// v1.5 — 2026-11-18 | Add eligibleForReaderRequestWaiver() — whether this reader/admin/editor
//                      can be counted as one of a customer's requested readers when booking a
//                      video call with them (the theme's Reader Request fee waiver). Readers
//                      and editors always are; admin isn't unless they opt in via the new
//                      video_consulting_reader_request_eligible column, since an admin doesn't
//                      actually write coverage by default.
// v1.4 — 2026-11-18 | updateReaderProfileShell() is also now used for an admin/editor's
//                      Favorite Genres/Films (ProfileController::updateFavorites()) — those
//                      columns live on ReaderProfile regardless of role, same as the
//                      video-consulting fields, so the name/doc below is no longer
//                      video-consulting-specific even though it started that way.
// v1.3 — 2026-11-18 | Add updateReaderProfileShell(), moved here from EditorProfileController's
//                      private copy so ProfileController's new self-serve enable/disable action
//                      (an admin/editor opting themselves into video consulting from their own
//                      Profile page, not just via another admin editing their profile) can reuse
//                      it instead of duplicating the ReaderProfile-seeding logic.
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

use App\Models\EditorProfile;
use App\Models\ReaderProfile;
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

    /**
     * Whether this person can be counted as one of a customer's requested readers when the
     * customer books a video call with them (theme-side: the Reader Request fee waiver). A
     * reader always can — it's literally their job. Admin/editor can't unless they explicitly
     * opt in on their Profile page, since neither writes coverage by default (an editor CAN,
     * but doesn't always — it's not automatic the way it is for a reader).
     */
    public static function eligibleForReaderRequestWaiver(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isReader()) {
            return true;
        }

        return (bool) $user->readerProfile?->video_consulting_reader_request_eligible;
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

    /**
     * An admin/editor acting on any ReaderProfile-keyed field for themselves — video
     * consulting (video_consulting_enabled/min_lead_hours, ReaderAvailabilityRule,
     * ReaderBlackoutDate, ReaderVideoConsultingRate) or favorite_genres/favorite_films —
     * uses the same tables a reader does rather than duplicating those columns onto
     * EditorProfile. A ReaderProfile row doesn't exist yet for most admin/editor users, so
     * creating one here seeds the NOT-NULL identity columns (initials/first_name/last_name/
     * etc.) from their EditorProfile, mirroring EditorProfileController::update()'s
     * reader<->editor role-change handoff (EditorProfile::sharedArrayFrom()) rather than
     * inventing a new convention.
     */
    public static function updateReaderProfileShell(User $user, array $data): ReaderProfile
    {
        $existing = $user->readerProfile;
        $shell = $existing ? [] : EditorProfile::sharedArrayFrom($user->editorProfile, $user);

        return $user->readerProfile()->updateOrCreate(['user_id' => $user->id], array_merge($shell, $data));
    }
}
