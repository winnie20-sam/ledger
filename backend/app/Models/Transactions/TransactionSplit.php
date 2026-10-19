<?php

namespace App\Models\Transactions;

use App\Models\Shared\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionSplit extends Model
{
    protected $table = 'transaction_splits';

    protected $fillable = [
        'transaction_id',
        'category_id',
        'amount',
    ];

    protected $hidden = [
        'transaction_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
