<?php

// v1.0 — 2026-06-18 | Notes-Only read credit packages — tracks purchased credits,
//                     remaining balance, persistent upload token, and 1-year expiry.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReadCreditPackage extends Model
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_EXHAUSTED = 'exhausted';

    protected $fillable = [
        'customer_email',
        'customer_name',
        'woo_order_number',
        'product_id',
        'credits_purchased',
        'credits_remaining',
        'upload_token',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'product_id'        => 'integer',
        'credits_purchased' => 'integer',
        'credits_remaining' => 'integer',
        'expires_at'        => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pkg) {
            if (empty($pkg->upload_token)) {
                $pkg->upload_token = (string) Str::uuid();
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                     ->where('expires_at', '>', now());
    }

    public function checkExpiration(): void
    {
        if ($this->status === self::STATUS_ACTIVE && $this->expires_at->isPast()) {
            $this->update(['status' => self::STATUS_EXPIRED]);
        }
    }

    public function isUsable(): bool
    {
        $this->checkExpiration();

        return $this->status === self::STATUS_ACTIVE
            && $this->credits_remaining > 0
            && $this->expires_at->isFuture();
    }

    public function useCredit(): bool
    {
        if ($this->credits_remaining <= 0) {
            return false;
        }

        $this->credits_remaining--;

        if ($this->credits_remaining === 0) {
            $this->status = self::STATUS_EXHAUSTED;
        }

        return $this->save();
    }

    public function packageLabel(): string
    {
        return $this->credits_purchased . '-Pack';
    }

    public function uploadUrl(): string
    {
        return rtrim(config('services.woocommerce.store_url', 'https://screenplayreaders.com'), '/')
            . '/upload-credits/?token=' . $this->upload_token;
    }
}
