<?php

// v1.0 — 2026-06-22 | Initial: tracks script registration orders from WooCommerce through
//                      certificate generation and email delivery. Supports unlimited token
//                      registrations with self-referencing parent relationship.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ScriptRegistration extends Model
{
    protected $table = 'script_registrations';

    public const VAR_FREE_90 = 55561;
    public const VAR_5YR     = 55562;
    public const VAR_10YR    = 55563;
    public const VAR_LIFETIME = 56735;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    public const VARIATION_LABELS = [
        self::VAR_FREE_90  => '90-Day',
        self::VAR_5YR      => '5-Year',
        self::VAR_10YR     => '10-Year',
        self::VAR_LIFETIME => 'Unlimited',
    ];

    protected $fillable = [
        'woo_order_id',
        'woo_order_number',
        'customer_name',
        'customer_email',
        'variation_id',
        'variation_label',
        'registration_id',
        'script_title',
        'page_count',
        'type_of_work',
        'author_first',
        'author_last',
        'additional_authors',
        'street_address',
        'city',
        'state_or_province',
        'postal_or_zip',
        'country',
        'phone',
        'unique_id',
        'email',
        'uploaded_file_url',
        'uploaded_file_name',
        'authcode',
        'registered_at',
        'expires_at',
        'drive_certificate_pdf_id',
        'unlimited_token',
        'unlimited_token_parent_id',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'variation_id' => 'integer',
            'page_count'   => 'integer',
            'registered_at' => 'datetime',
            'expires_at'    => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'unlimited_token_parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'unlimited_token_parent_id');
    }

    public function isUnlimited(): bool
    {
        return $this->variation_id === self::VAR_LIFETIME;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function publicRegistrationUrl(): ?string
    {
        if (! $this->unlimited_token) {
            return null;
        }

        return route('script-registration.public', $this->unlimited_token);
    }

    public static function generateRegistrationId(int $length = 12): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    public static function generateUnlimitedToken(): string
    {
        return Str::random(48);
    }
}
