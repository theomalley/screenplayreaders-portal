<?php

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
        'photo',
        'paypal_email',
        'availability',
        'availability_message',
        'upload_warning',
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
