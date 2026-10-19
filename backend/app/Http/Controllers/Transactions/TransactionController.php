<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\BaseController;
use App\Models\Transactions\Transaction;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionController extends BaseController
{
    /**
     * Columns the list may be sorted by
     */
    const SORTABLE = ['date', 'amount', 'description', 'created_at'];

    /**
     * Paginated, filtered, sorted list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'page_size' => 'nullable|integer|min:1|max:' . $this->const::MAX_PAGE_SIZE,
                'search' => 'nullable|string|max:150',
                'type' => ['nullable', Rule::in($this->const::TYPES)],
                'category_id' => 'nullable|integer',
                'date_from' => 'nullable|date_format:Y-m-d',
                'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
                'amount_min' => 'nullable|numeric|min:0',
                'amount_max' => 'nullable|numeric|min:0',
                'sort_by' => ['nullable', Rule::in(self::SORTABLE)],
                'sort_dir' => 'nullable|in:asc,desc',
            ]);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            return $this->paginate($request, $this->searchQuery($request));
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function searchQuery(Request $request)
    {
        $sortBy = $request->input('sort_by', 'date');
        $sortDir = $request->input('sort_dir', 'desc');

        return Transaction::with(['category', 'splits.category'])
            ->where('user_id', $this->userId())
            ->when($request->filled('search'), fn ($q) => $q->where('description', 'like', '%' . $request->search . '%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('category_id'), fn ($q) => $q->where(function ($q) use ($request) {
                // A split transaction matches if any of its splits is in the category
                $q->where('category_id', $request->category_id)
                    ->orWhereHas('splits', fn ($s) => $s->where('category_id', $request->category_id));
            }))
            ->when($request->filled('date_from'), fn ($q) => $q->where('date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->where('date', '<=', $request->date_to))
            ->when($request->filled('amount_min'), fn ($q) => $q->where('amount', '>=', $request->amount_min))
            ->when($request->filled('amount_max'), fn ($q) => $q->where('amount', '<=', $request->amount_max))
            ->orderBy($sortBy, $sortDir)
            ->orderBy('id', $sortDir); // tie-breaker so pages never repeat or skip rows
    }

    /**
     * One transaction with its category and splits
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecordDetails(Request $request): JsonResponse
    {
        $transaction = $this->findOwn($request->id);
        if (!$transaction) {
            return $this->notFound();
        }

        return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, $transaction->load(['category', 'splits.category']));
    }

    /**
     * Create a transaction, with either category_id or splits
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = $this->validatePayload($request);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $transaction = DB::transaction(function () use ($request) {
                $transaction = Transaction::create($this->payload($request) + ['user_id' => $this->userId()]);
                $this->saveSplits($transaction, $request);

                return $transaction;
            });

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_CREATED, $transaction->load(['category', 'splits.category']));
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * Update a transaction (send the full record, same fields as store, plus id)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function editRecord(Request $request): JsonResponse
    {
        try {
            $transaction = $this->findOwn($request->id);
            if (!$transaction) {
                return $this->notFound();
            }

            $validator = $this->validatePayload($request);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            DB::transaction(function () use ($transaction, $request) {
                $transaction->update($this->payload($request));
                $transaction->splits()->delete();
                $this->saveSplits($transaction, $request);
            });

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_UPDATED, $transaction->fresh(['category', 'splits.category']));
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * Delete one transaction ({id}) or several ({ids: [...]})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required_without:ids|integer',
                'ids' => 'required_without:id|array|min:1|max:' . $this->const::MAX_PAGE_SIZE,
                'ids.*' => 'integer',
            ]);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $ids = $request->filled('ids') ? $request->ids : [$request->id];
            $deleted = Transaction::where('user_id', $this->userId())->whereIn('id', $ids)->delete();
            if ($deleted === 0) {
                return $this->notFound();
            }

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_DELETED, ['deleted' => $deleted]);
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * Bulk create from a CSV import. All rows are saved, or none are.
     * Errors come back keyed by row, e.g. "transactions.3.amount".
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'transactions' => 'required|array|min:1|max:' . $this->const::MAX_IMPORT_ROWS,
                'transactions.*.description' => 'required|string|max:150',
                'transactions.*.amount' => 'required|numeric|min:0.01|max:9999999999',
                'transactions.*.type' => ['required', Rule::in($this->const::TYPES)],
                'transactions.*.date' => 'required|date_format:Y-m-d',
                'transactions.*.category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->userId())],
            ], [], [
                'transactions.*.description' => 'description',
                'transactions.*.amount' => 'amount',
                'transactions.*.type' => 'type',
                'transactions.*.date' => 'date',
                'transactions.*.category_id' => 'category',
            ]);
            $validator->after(function ($validator) use ($request) {
                foreach ((array) $request->transactions as $index => $row) {
                    if (isset($row['category_id'], $row['type']) && !$this->categoryMatchesType($row['category_id'], $row['type'])) {
                        $validator->errors()->add("transactions.$index.category_id", $this->const::RESPONSE_MESSAGE_CATEGORY_TYPE_MISMATCH);
                    }
                }
            });
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $now = now();
            $rows = collect($request->transactions)->map(fn ($row) => [
                'user_id' => $this->userId(),
                'category_id' => $row['category_id'],
                'description' => trim($row['description']),
                'amount' => round((float) $row['amount'], 2),
                'type' => $row['type'],
                'date' => $row['date'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::transaction(fn () => $rows->chunk(100)->each(fn ($chunk) => Transaction::insert($chunk->all())));

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_CREATED, ['imported' => $rows->count()]);
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Validation\Validator
     */
    private function validatePayload(Request $request)
    {
        $hasSplits = !empty($request->splits);
        $ownCategory = Rule::exists('categories', 'id')->where('user_id', $this->userId());

        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0.01|max:9999999999',
            'type' => ['required', Rule::in($this->const::TYPES)],
            'date' => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string|max:1000',
            'category_id' => $hasSplits ? 'nullable' : ['required', 'integer', $ownCategory],
            'splits' => 'nullable|array|min:2|max:10',
            'splits.*.category_id' => ['required', 'integer', $ownCategory],
            'splits.*.amount' => 'required|numeric|min:0.01',
        ], [
            'category_id.required' => 'Choose a category, or split the transaction.',
            'splits.min' => 'A split needs at least 2 parts.',
        ]);

        $validator->after(function ($validator) use ($request, $hasSplits) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($hasSplits) {
                $total = round(collect($request->splits)->sum(fn ($split) => (float) $split['amount']), 2);
                if ($total !== round((float) $request->amount, 2)) {
                    $validator->errors()->add('splits', $this->const::RESPONSE_MESSAGE_SPLITS_TOTAL_MISMATCH);
                }
                foreach ($request->splits as $index => $split) {
                    if (!$this->categoryMatchesType($split['category_id'], $request->type)) {
                        $validator->errors()->add("splits.$index.category_id", $this->const::RESPONSE_MESSAGE_CATEGORY_TYPE_MISMATCH);
                    }
                }
            } elseif (!$this->categoryMatchesType($request->category_id, $request->type)) {
                $validator->errors()->add('category_id', $this->const::RESPONSE_MESSAGE_CATEGORY_TYPE_MISMATCH);
            }
        });

        return $validator;
    }

    /**
     * @param Request $request
     * @return array
     */
    private function payload(Request $request): array
    {
        return [
            'category_id' => empty($request->splits) ? $request->category_id : null,
            'description' => trim($request->description),
            'amount' => round((float) $request->amount, 2),
            'type' => $request->type,
            'date' => $request->date,
            'notes' => $request->notes,
        ];
    }

    /**
     * @param Transaction $transaction
     * @param Request $request
     * @return void
     */
    private function saveSplits(Transaction $transaction, Request $request): void
    {
        foreach ((array) $request->splits as $split) {
            $transaction->splits()->create([
                'category_id' => $split['category_id'],
                'amount' => round((float) $split['amount'], 2),
            ]);
        }
    }

    /**
     * @param $categoryId
     * @param $type
     * @return bool
     */
    private function categoryMatchesType($categoryId, $type): bool
    {
        return DB::table('categories')
            ->where('id', $categoryId)
            ->where('user_id', $this->userId())
            ->where('type', $type)
            ->exists();
    }

    /**
     * @param $id
     * @return Transaction|null
     */
    private function findOwn($id): ?Transaction
    {
        return Transaction::where('user_id', $this->userId())->find($id);
    }
}
