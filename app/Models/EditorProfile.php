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
}
