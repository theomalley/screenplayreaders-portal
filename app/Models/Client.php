<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'code',
        'sr_address',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postcode',
        'country',
        'email',
        'notes',
        'last_invoice_number',
        'invoice_type',
        'stripe_customer_id',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function nextInvoiceNumber(): int
    {
        return $this->last_invoice_number + 1;
    }

    public function incrementInvoiceNumber(): void
    {
        $this->increment('last_invoice_number');
    }

    public function billingAddress(): string
    {
        return implode(', ', array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ]));
    }
}
