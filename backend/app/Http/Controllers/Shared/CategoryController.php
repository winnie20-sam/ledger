<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\BaseController;
use App\Models\Budgets\Budget;
use App\Models\Shared\Category;
use App\Models\Transactions\Transaction;
use App\Models\Transactions\TransactionSplit;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends BaseController
{
    /**
     * All the user's categories, for dropdowns. Optional ?type=income|expense
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fetch(Request $request): JsonResponse
    {
        $categories = Category::where('user_id', $this->userId())
            ->when(in_array($request->type, $this->const::TYPES), fn ($q) => $q->where('type', $request->type))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, $categories);
    }

    /**
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

            $category = Category::create($this->payload($request) + ['user_id' => $this->userId()]);

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_CREATED, $category);
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function editRecord(Request $request): JsonResponse
    {
        try {
            $category = $this->findOwn($request->id);
            if (!$category) {
                return $this->notFound();
            }

            $validator = $this->validatePayload($request, $category->id);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            // The type can't change once transactions use the category
            $payload = $this->payload($request);
            if ($this->isInUse($category->id)) {
                unset($payload['type']);
            }
            $category->update($payload);

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_UPDATED, $category->fresh());
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
            $category = $this->findOwn($request->id);
            if (!$category) {
                return $this->notFound();
            }
            if ($this->isInUse($category->id)) {
                return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $this->const::RESPONSE_MESSAGE_CATEGORY_IN_USE, null);
            }

            $category->delete();

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_DELETED, $request->id);
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * @param Request $request
     * @param int|null $ignoreId
     * @return \Illuminate\Validation\Validator
     */
    private function validatePayload(Request $request, ?int $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('categories', 'name')
                    ->where('user_id', $this->userId())
                    ->where('type', $request->type)
                    ->ignore($ignoreId),
            ],
            'type' => ['required', Rule::in($this->const::TYPES)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'name.unique' => 'You already have a category with this name.',
            'color.regex' => 'Color must be a hex value like #1a2b3c.',
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    private function payload(Request $request): array
    {
        return array_filter([
            'name' => trim($request->name),
            'type' => $request->type,
            'color' => $request->color,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param $id
     * @return Category|null
     */
    private function findOwn($id): ?Category
    {
        return Category::where('user_id', $this->userId())->find($id);
    }

    /**
     * @param int $categoryId
     * @return bool
     */
    private function isInUse(int $categoryId): bool
    {
        return Transaction::where('category_id', $categoryId)->exists()
            || TransactionSplit::where('category_id', $categoryId)->exists()
            || Budget::where('category_id', $categoryId)->exists();
    }
}
