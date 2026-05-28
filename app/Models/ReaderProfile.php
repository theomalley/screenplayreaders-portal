<?php

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
        'photo',
        'max_concurrent_assignments',
        'requests_bypass_capacity',
        'paypal_email',
        'phone',
        'sms_notifications',
        'sms_notify_any',
        'sms_notify_rush',
        'sms_notify_requests',
        'email_notifications',
        'email_notify_any',
        'email_notify_rush',
        'email_notify_requests',
        'availability',
        'availability_message',
        'upload_warning',
        'timezone',
    ];

    protected $casts = [
        'requests_bypass_capacity' => 'boolean',
        'sms_notifications'    => 'boolean',
        'sms_notify_any'       => 'boolean',
        'sms_notify_rush'      => 'boolean',
        'sms_notify_requests'  => 'boolean',
        'email_notifications'  => 'boolean',
        'email_notify_any'     => 'boolean',
        'email_notify_rush'    => 'boolean',
        'email_notify_requests'=> 'boolean',
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

    public function isAtCapacity(bool $isRequestedAssignment = false): bool
    {
        if ($isRequestedAssignment && $this->requests_bypass_capacity) {
            return false;
        }

        $override = (int) Setting::getValue('capacity_override', 0);
        $max = $override > 0 ? $override : (int) $this->max_concurrent_assignments;

        return $this->user->assignments()
                          ->where('status', Assignment::STATUS_ASSIGNED)
                          ->count() >= $max;
    }

    public function displayName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
