<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    // FIX: SoftDeletes добавлен для защиты финансовой истории.
    // Риск: hard delete с cascadeOnDelete уничтожал orders + order_transactions.
    // Теперь: $customer->delete() заполняет deleted_at, данные остаются в БД.

    protected $fillable = ['name', 'phone', 'address'];

    protected $dates = ['deleted_at'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
