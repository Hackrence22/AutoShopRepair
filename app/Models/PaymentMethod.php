<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'account_name',
        'account_number',
        'role_type',
        'description',
        'is_active',
        'sort_order',
        'shop_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeByRoleType($query, $roleType)
    {
        return $query->where('role_type', $roleType);
    }

    public function getRoleTypeLabelAttribute()
    {
        return match($this->role_type) {
            'gcash' => 'GCash',
            'paymaya' => 'PayMaya',
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'other' => 'Other',
            default => 'Unknown'
        };
    }

    public function getRoleTypeBadgeClassAttribute()
    {
        return match($this->role_type) {
            'gcash' => 'bg-success',
            'paymaya' => 'bg-primary',
            'cash' => 'bg-warning',
            'bank_transfer' => 'bg-info',
            'other' => 'bg-secondary',
            default => 'bg-secondary'
        };
    }

    public function getImageUrlAttribute()
    {
        if ($this->image && !empty($this->image)) {
            return asset('storage/payment-methods/' . $this->image);
        }
        // Fallbacks when no custom image is set
        if (function_exists('public_path')) {
            $cashPath = public_path('images/cash.png');
            if ($cashPath && file_exists($cashPath)) {
                return asset('images/cash.png');
            }
        }
        return asset('images/default-shop.png');
    }
}
