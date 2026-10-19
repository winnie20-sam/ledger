<?php

namespace Database\Seeders;

use App\Models\Budgets\Budget;
use App\Models\Shared\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo account with ~6 months of realistic (KES) transactions and budgets.
 * Login: demo@ledger.test / password123
 * Safe to re-run: the demo account is deleted and rebuilt each time.
 */
class DemoSeeder extends Seeder
{
    const EMAIL = 'demo@ledger.test';
    const PASSWORD = 'password123';
    const MONTHS = 6;

    private array $categories = [];
    private array $rows = [];
    private int $userId;

    public function run(): void
    {
        mt_srand(2026); // same data every run

        DB::transaction(function () {
            // Transactions/budgets first: categories can't be deleted while anything still points at them
            $oldUserId = User::where('email', self::EMAIL)->value('id');
            if ($oldUserId) {
                Transaction::where('user_id', $oldUserId)->delete(); // splits cascade with them
                Budget::where('user_id', $oldUserId)->delete();
                User::where('id', $oldUserId)->delete(); // categories cascade with the user
            }

            $user = User::create(['name' => 'Demo User', 'email' => self::EMAIL, 'password' => self::PASSWORD]);
            $this->userId = $user->id;
            Category::createDefaultsFor($user->id);
            $this->categories = Category::where('user_id', $user->id)->pluck('id', 'name')->all();

            $today = Carbon::today();
            $month = $today->copy()->startOfMonth()->subMonths(self::MONTHS - 1);
            while ($month->lte($today)) {
                $this->seedMonth($month->copy(), $today);
                $month->addMonth();
            }

            collect($this->rows)->sortBy('date')->chunk(100)->each(fn ($chunk) => Transaction::insert($chunk->values()->all()));
            $this->seedSplitShops($today);
            $this->seedBudgets($today);
        });

        $this->command?->info('Demo account: ' . self::EMAIL . ' / ' . self::PASSWORD);
    }

    private function seedMonth(Carbon $month, Carbon $today): void
    {
        // Fixed monthly items
        $this->add($month, 1, 'Rent - Kilimani apartment', 35000, 'Rent', $today);
        $this->add($month, 25, 'Salary - Acme Ltd', 120000, 'Salary', $today);
        $this->add($month, 5, 'Safaricom Home Fibre', 3999, 'Utilities', $today);
        $this->add($month, 8, 'KPLC electricity tokens', $this->money(2200, 4500), 'Utilities', $today);
        $this->add($month, 10, 'Nairobi Water', $this->money(700, 1500), 'Utilities', $today);
        $this->add($month, 3, 'Netflix', 1100, 'Entertainment', $today);
        $this->add($month, 3, 'Spotify', 390, 'Entertainment', $today);
        $this->add($month, 2, 'Gym membership', 4000, 'Health', $today);

        // Variable items
        $this->repeat($month, $today, mt_rand(1, 2), 'Freelance', [8000, 30000], ['Freelance - website project', 'Freelance - logo design', 'Freelance - consulting']);
        $this->repeat($month, $today, mt_rand(0, 1), 'Other income', [500, 5000], ['M-Pesa from friend', 'Refund - Jumia order', 'Chama payout']);
        $this->repeat($month, $today, mt_rand(9, 13), 'Food & Groceries', [200, 1800], ['Naivas', 'Carrefour', 'Quickmart', 'Java House', 'KFC', 'Uber Eats', 'Glovo', 'Mama Oliech']);
        $this->repeat($month, $today, mt_rand(10, 15), 'Transport', [150, 900], ['Uber', 'Bolt', 'Matatu fare', 'Little Cab']);
        $this->repeat($month, $today, mt_rand(1, 2), 'Transport', [3000, 6000], ['Fuel - Shell', 'Fuel - Rubis']);
        $this->repeat($month, $today, mt_rand(1, 3), 'Entertainment', [600, 3500], ['Cinema - Century', 'Concert tickets', 'Showmax', 'Bowling with friends']);
        $this->repeat($month, $today, mt_rand(0, 2), 'Health', [400, 3500], ['Pharmacy - Goodlife', 'Doctor visit', 'Lab tests']);
        $this->repeat($month, $today, mt_rand(1, 3), 'Shopping', [800, 7000], ['Jumia', 'Clothes - Mr Price', 'Kilimall', 'Shoes - Bata']);
        $this->repeat($month, $today, mt_rand(1, 2), 'Household', [300, 2500], ['Cleaning supplies', 'Gas refill', 'Kitchen items']);
    }

    /**
     * One big supermarket shop per month, split across Food & Groceries and Household
     */
    private function seedSplitShops(Carbon $today): void
    {
        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $date = $today->copy()->startOfMonth()->subMonths($i)->day(14);
            if ($date->gt($today)) {
                continue;
            }
            $food = $this->money(6000, 11000);
            $household = $this->money(1500, 4000);

            $transaction = Transaction::create([
                'user_id' => $this->userId,
                'category_id' => null,
                'description' => 'Carrefour - monthly shop',
                'amount' => round($food + $household, 2),
                'type' => 'expense',
                'date' => $date->toDateString(),
                'notes' => 'Split between groceries and household items',
            ]);
            $transaction->splits()->createMany([
                ['category_id' => $this->categories['Food & Groceries'], 'amount' => $food],
                ['category_id' => $this->categories['Household'], 'amount' => $household],
            ]);
        }
    }

    private function seedBudgets(Carbon $today): void
    {
        $budgets = [
            'Rent' => 35000, 'Food & Groceries' => 22000, 'Transport' => 12000, 'Utilities' => 9000,
            'Entertainment' => 5000, 'Shopping' => 8000, 'Health' => 6000, 'Household' => 5000,
        ];
        foreach ([$today->copy()->subMonth(), $today] as $month) {
            foreach ($budgets as $name => $amount) {
                Budget::create(['user_id' => $this->userId, 'category_id' => $this->categories[$name], 'month' => $month->format('Y-m'), 'amount' => $amount]);
            }
        }
    }

    private function repeat(Carbon $month, Carbon $today, int $times, string $category, array $range, array $descriptions): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->add($month, mt_rand(1, $month->daysInMonth), $descriptions[array_rand($descriptions)], $this->money(...$range), $category, $today);
        }
    }

    private function add(Carbon $month, int $day, string $description, float $amount, string $category, Carbon $today): void
    {
        $date = $month->copy()->day(min($day, $month->daysInMonth));
        if ($date->gt($today)) {
            return;
        }
        $type = in_array($category, ['Salary', 'Freelance', 'Other income']) ? 'income' : 'expense';
        $this->rows[] = [
            'user_id' => $this->userId,
            'category_id' => $this->categories[$category],
            'description' => $description,
            'amount' => $amount,
            'type' => $type,
            'date' => $date->toDateString(),
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Random amount, rounded to the nearest 10 (looks like real prices)
     */
    private function money(int $min, int $max): float
    {
        return (float) (round(mt_rand($min, $max) / 10) * 10);
    }
}
