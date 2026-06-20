<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadProfilePhotoRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Authenticate with email or phone + password and issue a token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('login'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        return $this->success([
            'token' => $result['token'],
            'user' => UserResource::make($result['user']),
        ], 'Login successful');
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(UserResource::make($request->user()->load('media')));
    }

    /**
     * Update the authenticated user's account details.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return $this->success(UserResource::make($user), 'Profile updated');
    }

    /**
     * Store/replace the authenticated user's account photo.
     */
    public function photo(UploadProfilePhotoRequest $request): JsonResponse
    {
        $user = $this->authService->setPhoto($request->user(), $request->file('photo'));

        return $this->success(UserResource::make($user), 'Profile photo updated');
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->validated('password'));

        return $this->success(null, 'Password changed successfully');
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out');
    }
}
