<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'client_id',
        'assignment_id',
        'invoice_number',
        'description',
        'amount',
        'status',
        'invoice_type',
        'stripe_invoice_id',
        'stripe_invoice_url',
        'google_doc_id',
        'helpscout_conversation_id',
        'notes',
        'due_date',
        'issued_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'    => 'decimal:2',
            'due_date'  => 'date',
            'issued_at' => 'datetime',
            'paid_at'   => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('created_at');
    }

    public function isOutstanding(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
