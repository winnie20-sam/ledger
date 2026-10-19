<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Shared\CustomResponseController;
use App\Models\Shared\CustomConstants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

class BaseController extends Controller
{
    public $resp, $const;

    /**
     * Base controller constructor
     */
    public function __construct()
    {
        $this->resp = new CustomResponseController();
        $this->const = new CustomConstants();
    }

    /**
     * Paginate a query
     *
     * @param Request $request
     * @param $query
     * @param string $content
     * @return JsonResponse
     */
    public function paginate(Request $request, $query, string $content = 'items'): JsonResponse
    {
        $pageSize = min(max((int) $request->input('page_size', $this->const::DEFAULT_PAGE_SIZE), 1), $this->const::MAX_PAGE_SIZE);
        $data = $query->paginate($pageSize, ['*'], 'page', max((int) $request->input('page', 1), 1));

        $formattedData = [
            'current_page' => $data->currentPage(),
            'page_size' => $data->perPage(),
            'last_page' => $data->lastPage(),
            'total' => $data->total(),
            $content => $data->items(),
        ];

        return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, $formattedData);
    }

    /**
     * Validation failure response: data holds the errors keyed by field name
     *
     * @param Validator $validator
     * @return JsonResponse
     */
    public function validationFailed(Validator $validator): JsonResponse
    {
        return $this->resp->response($this->const::RESPONSE_STATUS_PAYLOAD_VALIDATION_FAIL, $this->const::RESPONSE_MESSAGE_PAYLOAD_VALIDATION_FAIL, $validator->errors()->toArray());
    }

    /**
     * Record not found response
     *
     * @return JsonResponse
     */
    public function notFound(): JsonResponse
    {
        return $this->resp->response($this->const::RESPONSE_STATUS_RECORD_NOT_FOUND, $this->const::RESPONSE_MESSAGE_RECORD_NOT_FOUND, null);
    }

    /**
     * Logged-in user's id
     *
     * @return int
     */
    public function userId(): int
    {
        return (int) auth()->id();
    }
}
