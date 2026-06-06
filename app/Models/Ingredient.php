<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'stock' => 'float',
        'cost'  => 'float',
    ];

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * Human-readable stock with unit label.
     */
    public function getStockLabelAttribute(): string
    {
        return number_format($this->stock, 2) . ' ' . $this->unit;
    }
}
