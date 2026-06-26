<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadProfilePhotoRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
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
            'user' => UserResource::make($result['user']->load(['branch', 'media', 'student'])),
        ], 'Login successful');
    }

    /**
     * Issue a one-time reset code to the account matching the login. Always
     * returns the same message so callers cannot probe which accounts exist.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetCode($request->validated('login'));

        return $this->success(null, 'If an account matches, a reset code has been sent.');
    }

    /**
     * Verify a reset code before showing the new-password form.
     */
    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $resetToken = $this->authService->verifyResetCode(
            $request->validated('login'),
            $request->validated('code'),
        );

        return $this->success([
            'reset_token' => $resetToken,
        ], 'Reset code verified.');
    }

    /**
     * Set a new password after a reset code has been verified.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->validated('reset_token'),
            $request->validated('password'),
        );

        return $this->success(null, 'Password has been reset. Please log in.');
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(UserResource::make($request->user()->load(['branch', 'media', 'student'])));
    }

    /**
     * Update the authenticated user's account details.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return $this->success(UserResource::make($user->load(['branch', 'media', 'student'])), 'Profile updated');
    }

    /**
     * Store/replace the authenticated user's account photo.
     */
    public function photo(UploadProfilePhotoRequest $request): JsonResponse
    {
        $user = $this->authService->setPhoto($request->user(), $request->file('photo'));

        return $this->success(UserResource::make($user->load(['branch', 'media', 'student'])), 'Profile photo updated');
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
