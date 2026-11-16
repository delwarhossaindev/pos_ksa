<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded(['id'])]
class Order extends Model
{
    public const TYPES = ['takeaway', 'dine_in', 'delivery'];

    public const PAYMENT_METHODS = ['cash', 'card'];

    public const CARD_TYPES = ['mada', 'visa', 'mastercard'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The ZATCA tax invoice issued for this sale in the active environment (a sandbox test invoice does not
     * count once the shop runs in production). Credit/debit notes are separate documents.
     */
    public function einvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)
            ->where('document_type', 'invoice')
            ->where('environment', ZatcaSetting::currentEnvironment())
            ->latestOfMany();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'takeaway' => __('Takeaway'),
            'dine_in' => __('Dine-in'),
            'delivery' => __('Delivery'),
            default => $type,
        };
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }
}
