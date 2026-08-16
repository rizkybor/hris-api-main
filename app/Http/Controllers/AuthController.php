<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LoginStoreRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\AuthRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private AuthRepositoryInterface $authRepository;

    public function __construct(AuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function login(LoginStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $user = $this->authRepository->login($request);

            activity('Security')
                ->causedBy($user)
                ->event('login')
                ->log('logged in');

            return ResponseHelper::jsonResponse(true, 'Login Successful', new UserResource($user), 200);
        } catch (\Exception $e) {
            activity('Security')
                ->withProperties(['email' => $request['email'] ?? null])
                ->event('login_failed')
                ->log('failed login attempt');

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, $e->getCode());
        }
    }

    public function me()
    {
        try {
            $user = $this->authRepository->me();

            return ResponseHelper::jsonResponse(true, 'Profile Retrieved Successfully', new UserResource($user), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function logout()
    {
        try {
            $user = $this->authRepository->logout();

            activity('Security')
                ->causedBy($user)
                ->event('logout')
                ->log('logged out');

            return ResponseHelper::jsonResponse(true, 'Logout Successful', new UserResource($user), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email'),
            function ($user, string $token) {
                $url = rtrim(config('app.frontend_url'), '/')."/auth/reset-password?token={$token}&email=".urlencode($user->email);
                $user->notify(new \App\Notifications\ResetPasswordLink($url));
            }
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return ResponseHelper::jsonResponse(true, 'If that email exists, a reset link has been sent.', null, 200);
        }

        return ResponseHelper::jsonResponse(true, 'Password reset link sent to your email.', null, 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ResponseHelper::jsonResponse(false, __($status), null, 422);
        }

        return ResponseHelper::jsonResponse(true, 'Password reset successfully. You can now log in.', null, 200);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $user = $this->authRepository->updateProfile([
                'name' => $request->input('name'),
                'password' => $request->input('password'),
                'profile_photo' => $request->file('profile_photo'),
            ]);

            return ResponseHelper::jsonResponse(true, 'Profile Updated Successfully', new UserResource($user), 200);
        } catch (\Exception $e) {
            $status = $e->getCode() ?: 500;

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, $status);
        }
    }
}
