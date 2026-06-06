<?php

// v1.0 — 2026-06-06 | Marketing email campaign model

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EmailCampaign extends Model
{
    protected $fillable = [
        'campaign_name', 'status', 'send_order', 'scheduled_at',
        'subject_line', 'preheader', 'headline_top', 'paragraph_top1', 'paragraph_top2',
        'url1', 'headline_bottom', 'paragraph_bottom', 'custom_html',
        'image_path', 'image_url',
        'coupon_code', 'coupon_amount', 'coupon_duration_days', 'coupon_type', 'coupon_product_ids',
        'mailerlite_group_id', 'mailerlite_campaign_id', 'woo_coupon_id',
        'test_sent_at', 'live_sent_at',
    ];

    protected $casts = [
        'scheduled_at'       => 'datetime',
        'test_sent_at'       => 'datetime',
        'live_sent_at'       => 'datetime',
        'coupon_product_ids' => 'array',
        'coupon_amount'      => 'decimal:2',
    ];

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', 'queued')->orderBy('send_order');
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'paused'])->orderByDesc('updated_at');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent')->orderByDesc('live_sent_at');
    }

    public function couponExpiryDate(): ?string
    {
        if (!$this->coupon_duration_days) {
            return null;
        }
        $base = $this->scheduled_at ?? now();
        return $base->copy()->addDays((int) $this->coupon_duration_days)->format('F j, Y');
    }
}
