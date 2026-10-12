<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Handle user login and issue a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = null;

        if (! empty($validated['user_id'])) {
            $user = User::find($validated['user_id']);
        } elseif (! empty($validated['login'])) {
            $loginInput = $validated['login'];
            $user = User::query()
                ->where('username', $loginInput)
                ->orWhere('email', $loginInput)
                ->orWhere('phone', $loginInput)
                ->orWhere('name', $loginInput)
                ->first();
        }

        if (! $user) {
            return ApiResponse::error('بيانات الدخول غير صحيحة.', 422);
        }

        if (! empty($validated['type'])) {
            $selectedType = UserType::tryFrom($validated['type']);
            if ($selectedType && $user->type !== $selectedType) {
                return ApiResponse::error(
                    "هذا الحساب غير مسجل كـ {$selectedType->label()}.",
                    403
                );
            }
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error('كلمة المرور غير صحيحة.', 422);
        }

        if (! $user->is_active) {
            return ApiResponse::error('تم تعطيل هذا الحساب، يرجى مراجعة إدارة النظام.', 403);
        }

        $deviceName = $validated['device_name'] ?? 'tcms-frontend';
        $token = $user->createToken($deviceName, [$user->type->value])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'تم تسجيل الدخول بنجاح.');
    }

    /**
     * Get the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()),
            'بيانات المستخدم الحالي.'
        );
    }

    /**
     * Handle user logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'تم تسجيل الخروج بنجاح.');
    }

    /**
     * Return list of available user roles/types.
     */
    public function roles(): JsonResponse
    {
        return ApiResponse::success(
            UserType::options(),
            'قائمة أنواع المستخدمين المتاحة في النظام.'
        );
    }

    /**
     * Return active users list for a specific type (e.g. for dropdown selection in login).
     */
    public function usersByType(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', Rule::enum(UserType::class)],
        ], [
            'type.required' => 'نوع المستخدم مطلوب.',
            'type.enum' => 'نوع المستخدم المحدد غير صالح.',
        ]);

        $users = User::query()
            ->where('type', $request->query('type'))
            ->where('is_active', true)
            ->select(['id', 'name', 'username'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            $users,
            'قائمة المستخدمين المتاحين للنوع المحدد.'
        );
    }
}
