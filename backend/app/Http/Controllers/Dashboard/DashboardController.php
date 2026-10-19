<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Budgets\BudgetController;
use App\Models\Shared\Category;
use App\Models\Transactions\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardController extends BaseController
{
    /**
     * Everything the dashboard needs in one call:
     * totals, spending by category, monthly trend and budget vs actual.
     * Defaults to the current month.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_from' => 'nullable|date_format:Y-m-d',
                'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
                'months' => 'nullable|integer|min:1|max:24',
            ]);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $from = $request->input('date_from', now()->startOfMonth()->toDateString());
            $to = $request->input('date_to', now()->endOfMonth()->toDateString());
            $userId = $this->userId();

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, [
                'date_from' => $from,
                'date_to' => $to,
                'totals' => $this->totals($userId, $from, $to),
                'by_category' => $this->byCategory($userId, $from, $to),
                'monthly_trend' => $this->monthlyTrend($userId, Carbon::parse($to), (int) $request->input('months', 6)),
                'budgets' => BudgetController::budgetsWithSpending($userId, Carbon::parse($to)->format('Y-m')),
            ]);
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * @return array{income: float, expense: float, balance: float, transaction_count: int}
     */
    private function totals(int $userId, string $from, string $to): array
    {
        $row = DB::table('transactions')
            ->where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense")
            ->selectRaw('COUNT(*) AS transaction_count')
            ->first();

        return [
            'income' => (float) $row->income,
            'expense' => (float) $row->expense,
            'balance' => round($row->income - $row->expense, 2),
            'transaction_count' => (int) $row->transaction_count,
        ];
    }

    /**
     * Totals per category, biggest first, with each one's share of its type's total
     */
    private function byCategory(int $userId, string $from, string $to): array
    {
        $totals = Transaction::totalsByCategory($userId, $from, $to);
        $categories = Category::where('user_id', $userId)->whereIn('id', array_keys($totals))->get();
        $typeTotals = $categories->groupBy('type')->map(fn ($group) => $group->sum(fn ($c) => $totals[$c->id]));

        return $categories
            ->map(fn ($category) => [
                'category_id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'total' => $totals[$category->id],
                'percent' => round($totals[$category->id] / max($typeTotals[$category->type], 0.01) * 100, 1),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Income/expense per month for the N months ending at $end's month (oldest first, gaps filled with 0)
     */
    private function monthlyTrend(int $userId, Carbon $end, int $months): array
    {
        $start = $end->copy()->startOfMonth()->subMonths($months - 1);

        $rows = DB::table('transactions')
            ->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->copy()->endOfMonth()->toDateString()])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS month")
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income")
            ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $trend = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i)->format('Y-m');
            $income = (float) ($rows[$month]->income ?? 0);
            $expense = (float) ($rows[$month]->expense ?? 0);
            $trend[] = ['month' => $month, 'income' => $income, 'expense' => $expense, 'balance' => round($income - $expense, 2)];
        }

        return $trend;
    }
}
