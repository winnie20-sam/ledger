<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Models\Shared\Category;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    /**
     * Register a new account (gets the default categories) and log it in
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = $this->validateRegisterPayload($request);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $user = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => strtolower($request->email),
                    'password' => $request->password,
                ]);
                Category::createDefaultsFor($user->id);

                return $user;
            });

            $token = auth()->login($user);

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_RECORD_CREATED, $this->createNewToken($token));
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Validation\Validator
     */
    private function validateRegisterPayload(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);
    }

    /**
     * Log in with email + password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
            if ($validator->fails()) {
                return $this->validationFailed($validator);
            }

            $credentials = ['email' => strtolower($request->email), 'password' => $request->password];
            if (!$token = auth()->attempt($credentials)) {
                return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $this->const::RESPONSE_MESSAGE_INVALID_LOGIN_CREDENTIALS, null);
            }

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, $this->createNewToken($token));
        } catch (Exception $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_FAILED, $e->getMessage(), null);
        }
    }

    /**
     * The logged-in user
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, $this->userDetails(auth()->user()));
    }

    /**
     * Invalidate the current token
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_LOGGED_OUT, null);
    }

    /**
     * Swap the current (still valid, or recently expired) token for a new one
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = auth()->refresh();
            auth()->setToken($token)->authenticate();

            return $this->resp->response($this->const::RESPONSE_STATUS_SUCCESS, $this->const::RESPONSE_MESSAGE_SUCCESS, $this->createNewToken($token));
        } catch (\Throwable $e) {
            return $this->resp->response($this->const::RESPONSE_STATUS_UNAUTHORIZED, $this->const::RESPONSE_MESSAGE_UNAUTHORIZED, null, 401);
        }
    }

    /**
     * @param string $token
     * @return array
     */
    protected function createNewToken(string $token): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60, // minutes to seconds
            'user' => $this->userDetails(auth()->user()),
        ];
    }

    /**
     * @param User $user
     * @return array
     */
    private function userDetails(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
