<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CustomResponseController extends Controller
{
    /**
     * Wraps every response as {"status":{"code","message"},"data"}
     *
     * @param $code
     * @param $message
     * @param $data
     * @param int $httpStatus
     * @return JsonResponse
     */
    public function response($code, $message, $data, int $httpStatus = 200): JsonResponse
    {
        return response()->json($this->getResponse($code, $message, $data), $httpStatus);
    }

    public function getResponse($code, $message, $data): array
    {
        return ['status' => ['code' => $code, 'message' => $message], 'data' => $data];
    }
}
