<?php

// v1.15 — 2026-07-11 | Add tier_0 (onboarding tier) — mutually exclusive with tier_1/tier_2,
//                      set/cleared by ReaderProfileController::update(). tiers() now includes 0.
// v1.14 — 2026-06-16 | isAtCapacity() accepts $isRushAssignment param; excludes exempt_from_capacity
//                      assignments from count; capacity_override_excludes_rush_requests applies to
//                      all caps (override and individual) — rush + reader-request assignments
//                      bypass the cap and are excluded from the active count when that setting is on.
// v1.13 — 2026-06-13 | Add notify_only_if_under_capacity flag — skip new-assignment
//                      notifications when the reader is at their assignment capacity.
// v1.12 — 2026-06-05 | Add tier_1/tier_2 fields; tiers() helper
// v1.11 — 2026-06-05 | Add about_photo fields to fillable
// v1.10 — 2026-06-03 | Add custom_message to fillable
// v1.9 — 2026-05-30 | Add followup notification fields to fillable
// v1.8 — 2026-05-30 | Add bio_pending and photo_pending to fillable
// v1.7 — 2026-05-30 | Add bio to fillable
// v1.6 — 2026-05-30 | Add title to fillable
// v1.5 — 2026-05-28 | Add timezone to fillable
// v1.4 — 2026-05-25 | Add requests_bypass_capacity flag; isAtCapacity() accepts $isRequestedAssignment param.
// v1.3 — 2026-05-24 | isAtCapacity() respects global capacity_override setting.
// v1.2 — 2026-05-24 | Add upload_warning field (customer-facing per-reader message on upload form)
// v1.1 — 2026-05-24 | Add availability + availability_message fields
// v1.0 — 2026-05-16 | Initial scaffold: reader profile linked 1:1 to users

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Setting;

class ReaderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'initials',
        'first_name',
        'last_name',
        'title',
        'bio',
        'custom_message',
        'bio_pending',
        'bio_rejection_note',
        'photo',
        'photo_pending',
        'photo_rejection_note',
        'about_photo',
        'about_photo_pending',
        'about_photo_rejection_note',
        'max_concurrent_assignments',
        'requests_bypass_capacity',
        'notify_only_if_under_capacity',
        'paypal_email',
        'is_1099',
        'phone',
        'sms_notifications',
        'sms_notify_any',
        'sms_notify_rush',
        'sms_notify_requests',
        'email_notifications',
        'email_notify_any',
        'email_notify_rush',
        'email_notify_requests',
        'email_notify_followup',
        'sms_notify_followup',
        'email_notify_qc_fail',
        'sms_notify_qc_fail',
        'tier_0',
        'tier_1',
        'tier_2',
        'availability',
        'availability_message',
        'upload_warning',
        'timezone',
    ];

    protected $casts = [
        'is_1099'                  => 'boolean',
        'requests_bypass_capacity' => 'boolean',
        'notify_only_if_under_capacity' => 'boolean',
        'sms_notifications'    => 'boolean',
        'sms_notify_any'       => 'boolean',
        'sms_notify_rush'      => 'boolean',
        'sms_notify_requests'  => 'boolean',
        'email_notifications'  => 'boolean',
        'email_notify_any'     => 'boolean',
        'email_notify_rush'    => 'boolean',
        'email_notify_requests' => 'boolean',
        'email_notify_followup' => 'boolean',
        'sms_notify_followup'   => 'boolean',
        'email_notify_qc_fail'  => 'boolean',
        'sms_notify_qc_fail'    => 'boolean',
        'tier_0'                => 'boolean',
        'tier_1'                => 'boolean',
        'tier_2'                => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Assignments currently held by this reader */
    public function activeAssignments(): HasMany
    {
        return $this->user->assignments()
                          ->whereIn('status', [Assignment::STATUS_ASSIGNED]);
    }

    public function isAtCapacity(bool $isRequestedAssignment = false, bool $isRushAssignment = false): bool
    {
        $override = (int) Setting::getValue('capacity_override', 0);
        $max      = $override > 0 ? $override : (int) $this->max_concurrent_assignments;

        // Applies to all caps (override and individual reader caps).
        $excludeRushRequests = (bool) Setting::getValue('capacity_override_excludes_rush_requests', true);

        // Rush/request assignments always bypass the cap when the setting is on.
        if ($excludeRushRequests && ($isRushAssignment || $isRequestedAssignment)) {
            return false;
        }

        // Per-reader fallback: bypass reader-requested assignments even when the global setting is off.
        if ($isRequestedAssignment && $this->requests_bypass_capacity) {
            return false;
        }

        $query = $this->user->assignments()
            ->where('status', Assignment::STATUS_ASSIGNED)
            ->where('exempt_from_capacity', false);

        // When the override excludes rush/requests, also exclude them from the active count.
        if ($excludeRushRequests) {
            $query->where('rush', false)
                  ->where(function ($q) {
                      $q->whereNull('requested_reader_id')
                        ->orWhere('requested_reader_id', '!=', $this->user_id);
                  });
        }

        return $query->count() >= $max;
    }

    /** Returns the tier numbers (0 = onboarding sandbox-only, and/or 1, 2) this reader belongs to. */
    public function tiers(): array
    {
        $t = [];
        if ($this->tier_0) $t[] = 0;
        if ($this->tier_1) $t[] = 1;
        if ($this->tier_2) $t[] = 2;
        return $t;
    }

    public function displayName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
