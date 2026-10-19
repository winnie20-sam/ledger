<?php

namespace App\Http\Controllers\Budgets;

use App\Http\Controllers\BaseController;
use App\Models\Budgets\Budget;
use App\Models\Transactions\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BudgetController extends BaseController
{
    /**
     * A month's budgets with what has been spent against each. Defaults to the current month.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'month' => 'nullable|date_format:Y-m',
            ]);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $month = $request->input('month', now()->format('Y-m'));

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, [
                'month' => $month,
                'items' => self::budgetsWithSpending($this->userId(), $month),
            ]);
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * Budgets for a month, each with spent / remaining / percent_used
     *
     * @param int $userId
     * @param string $month Y-m
     * @return array
     */
    public static function budgetsWithSpending(int $userId, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01');
        $spent = Transaction::totalsByCategory($userId, $start->toDateString(), $start->copy()->endOfMonth()->toDateString());

        return Budget::with('category')
            ->where('user_id', $userId)
            ->where('month', $month)
            ->get()
            ->sortBy(fn ($budget) => $budget->category->name)
            ->values()
            ->map(function ($budget) use ($spent) {
                $used = $spent[$budget->category_id] ?? 0;

                return $budget->toArray() + [
                    'spent' => $used,
                    'remaining' => round($budget->amount - $used, 2),
                    'percent_used' => $budget->amount > 0 ? round($used / $budget->amount * 100, 1) : 0,
                ];
            })
            ->all();
    }

    /**
     * Set a budget for an expense category in a month
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => [
                    'required', 'integer',
                    Rule::exists('categories', 'id')->where('user_id', $this->userId())->where('type', $this->const::TYPE_EXPENSE),
                ],
                'month' => 'required|date_format:Y-m',
                'amount' => 'required|numeric|min:1|max:9999999999',
            ], [
                'category_id.exists' => 'Budgets can only be set on your own expense categories.',
            ]);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $exists = Budget::where('user_id', $this->userId())
                ->where('category_id', $request->category_id)
                ->where('month', $request->month)
                ->exists();
            if ($exists) {
                return $this->resp->response($this->const::RESPONSE_STATUS_RECORD_EXISTS, 'This category already has a budget for that month', null);
            }

            $budget = Budget::create([
                'user_id' => $this->userId(),
                'category_id' => $request->category_id,
                'month' => $request->month,
                'amount' => round((float) $request->amount, 2),
            ]);

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_CREATED, $budget->load('category'));
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * Change a budget's amount
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function editRecord(Request $request): JsonResponse
    {
        try {
            $budget = Budget::where('user_id', $this->userId())->find($request->id);
            if (!$budget) {
                return $this->notFound();
            }

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1|max:9999999999',
            ]);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $budget->update(['amount' => round((float) $request->amount, 2)]);

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_UPDATED, $budget->load('category'));
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $deleted = Budget::where('user_id', $this->userId())->where('id', $request->id)->delete();
            if ($deleted === 0) {
                return $this->notFound();
            }

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_DELETED, $request->id);
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }
}
