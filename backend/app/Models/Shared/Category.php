<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'color',
    ];

    protected $hidden = [
        'user_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Categories every new account starts with
     */
    const DEFAULTS = [
        ['name' => 'Salary', 'type' => 'income', 'color' => '#16a34a'],
        ['name' => 'Freelance', 'type' => 'income', 'color' => '#0d9488'],
        ['name' => 'Other income', 'type' => 'income', 'color' => '#65a30d'],
        ['name' => 'Rent', 'type' => 'expense', 'color' => '#dc2626'],
        ['name' => 'Food & Groceries', 'type' => 'expense', 'color' => '#ea580c'],
        ['name' => 'Transport', 'type' => 'expense', 'color' => '#2563eb'],
        ['name' => 'Utilities', 'type' => 'expense', 'color' => '#7c3aed'],
        ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#db2777'],
        ['name' => 'Health', 'type' => 'expense', 'color' => '#0891b2'],
        ['name' => 'Shopping', 'type' => 'expense', 'color' => '#ca8a04'],
        ['name' => 'Household', 'type' => 'expense', 'color' => '#4b5563'],
    ];

    /**
     * Gives a user the default categories
     *
     * @param int $userId
     * @return void
     */
    public static function createDefaultsFor(int $userId): void
    {
        foreach (self::DEFAULTS as $category) {
            self::create($category + ['user_id' => $userId]);
        }
    }
}
