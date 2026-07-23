<?php

// v1.10 — 2026-07-08 | Add is_1099 to fillable/casts
// v1.9 — 2026-06-05 | Add about_photo_pending and about_photo_rejection_note fields
// v1.8 — 2026-06-04 | Add about_photo for main website About page
// v1.7 — 2026-06-03 | Add custom_message to fillable
// v1.6 — 2026-05-30 | Add bio_pending and photo_pending to fillable
// v1.5 — 2026-05-30 | Add bio to fillable
// v1.4 — 2026-05-30 | Add title to fillable
// v1.3 — 2026-05-28 | Add editor_commission and editor_weekly_flat
// v1.2 — 2026-05-28 | Add timezone to fillable
// v1.1 — 2026-05-25 | Add productCommissions relationship
// v1.0 — 2026-05-24 | Initial scaffold: editor profile linked 1:1 to users with role=editor

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorProfile extends Model
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
        'paypal_email',
        'is_1099',
        'availability',
        'availability_message',
        'upload_warning',
        'timezone',
        'editor_commission',
        'editor_weekly_flat',
        'about_photo',
        'about_photo_pending',
        'about_photo_rejection_note',
    ];

    protected $casts = [
        'editor_commission'  => 'float',
        'editor_weekly_flat' => 'float',
        'is_1099'            => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productCommissions(): HasMany
    {
        return $this->hasMany(EditorProductCommission::class);
    }

    /** Keyed by woo_product_id for fast lookup. */
    public function productCommissionsKeyed(): \Illuminate\Support\Collection
    {
        return $this->productCommissions->keyBy('woo_product_id');
    }

    public function displayName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Field set shared with ReaderProfile, used by EditorProfileController::update()
     * when converting an editor to a reader (role_change) — carries bio/photo/
     * availability/etc. across the role boundary. $profile may be null (e.g. an
     * editor who never had a profile row); falls back to name-derived defaults.
     */
    public static function sharedArrayFrom(?self $profile, User $user): array
    {
        $nameParts = explode(' ', $user->name, 2);

        return [
            'initials'                   => $profile?->initials ?? strtoupper(substr($user->name, 0, 2)),
            'first_name'                 => $profile?->first_name ?? ($nameParts[0] ?? ''),
            'last_name'                  => $profile?->last_name ?? ($nameParts[1] ?? ''),
            'title'                      => $profile?->title,
            'bio'                        => $profile?->bio,
            'bio_pending'                => $profile?->bio_pending,
            'bio_rejection_note'         => $profile?->bio_rejection_note,
            'custom_message'             => $profile?->custom_message,
            'photo'                      => $profile?->photo,
            'photo_pending'              => $profile?->photo_pending,
            'photo_rejection_note'       => $profile?->photo_rejection_note,
            'about_photo'                => $profile?->about_photo,
            'about_photo_pending'        => $profile?->about_photo_pending,
            'about_photo_rejection_note' => $profile?->about_photo_rejection_note,
            'paypal_email'               => $profile?->paypal_email,
            'availability'               => $profile?->availability ?? 'available',
            'availability_message'       => $profile?->availability_message,
            'upload_warning'             => $profile?->upload_warning,
            'timezone'                   => $profile?->timezone,
        ];
    }
}
