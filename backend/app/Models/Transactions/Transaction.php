<?php

namespace App\Models\Transactions;

use App\Models\Shared\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'category_id',
        'description',
        'amount',
        'type',
        'date',
        'notes',
    ];

    protected $hidden = [
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'date' => 'date:Y-m-d',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    /**
     * Total per category between two dates (inclusive), counting split
     * transactions under each of their split categories.
     *
     * @param int $userId
     * @param string $from Y-m-d
     * @param string $to Y-m-d
     * @return array [category_id => total]
     */
    public static function totalsByCategory(int $userId, string $from, string $to): array
    {
        $whole = DB::table('transactions')
            ->where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) AS total')
            ->pluck('total', 'category_id');

        $split = DB::table('transaction_splits')
            ->join('transactions', 'transactions.id', '=', 'transaction_splits.transaction_id')
            ->where('transactions.user_id', $userId)
            ->whereBetween('transactions.date', [$from, $to])
            ->groupBy('transaction_splits.category_id')
            ->selectRaw('transaction_splits.category_id, SUM(transaction_splits.amount) AS total')
            ->pluck('total', 'category_id');

        $totals = [];
        foreach ([$whole, $split] as $set) {
            foreach ($set as $categoryId => $total) {
                $totals[$categoryId] = round(($totals[$categoryId] ?? 0) + (float) $total, 2);
            }
        }

        return $totals;
    }
}
