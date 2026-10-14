<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\StoreOwnerRequest;
use App\Http\Requests\GeneralManager\UpdateOwnerRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OwnerManagementController extends Controller
{
    /**
     * Display a listing of all owners with search and status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('type', UserType::Owner->value);

        // Search by name, national_id, phone, username, or email
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status (active / inactive)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $allOwnersCount = User::where('type', UserType::Owner->value)->count();
        $activeCount = User::where('type', UserType::Owner->value)->where('is_active', true)->count();
        $inactiveCount = User::where('type', UserType::Owner->value)->where('is_active', false)->count();

        $statistics = [
            'all_count' => $allOwnersCount,
            'active_count' => $activeCount,
            'inactive_count' => $inactiveCount,
        ];

        $tabsCounts = [
            'supervisors' => User::whereIn('type', array_map(fn (UserType $t): string => $t->value, UserType::supervisorTypes()))->count(),
            'collectors' => User::where('type', UserType::Collector->value)->count(),
            'owners' => $allOwnersCount,
        ];

        if ($perPage = $request->query('per_page')) {
            $owners = $query->latest('id')->paginate((int) $perPage);

            return ApiResponse::success([
                'owners' => UserResource::collection($owners),
                'pagination' => [
                    'total' => $owners->total(),
                    'per_page' => $owners->perPage(),
                    'current_page' => $owners->currentPage(),
                    'last_page' => $owners->lastPage(),
                ],
                'statistics' => $statistics,
                'tabs_counts' => $tabsCounts,
            ], 'تم جلب قائمة الملاك بنجاح.');
        }

        $owners = $query->latest('id')->get();

        return ApiResponse::success([
            'owners' => UserResource::collection($owners),
            'statistics' => $statistics,
            'tabs_counts' => $tabsCounts,
        ], 'تم جلب قائمة الملاك بنجاح.');
    }

    /**
     * Store a newly created owner.
     */
    public function store(StoreOwnerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $username = $validated['username'] ?? null;
        if (empty($username)) {
            $baseUsername = ! empty($validated['national_id']) ? 'owner_'.$validated['national_id'] : 'owner';
            $username = $baseUsername;

            while (User::where('username', $username)->exists()) {
                $username = $baseUsername.'_'.Str::lower(Str::random(4));
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'national_id' => $validated['national_id'],
            'username' => $username,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'type' => UserType::Owner,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return ApiResponse::success(
            new UserResource($user),
            'تمت إضافة المالك بنجاح.',
            201
        );
    }

    /**
     * Display the specified owner details.
     */
    public function show(User $owner): JsonResponse
    {
        if (! $owner->isOwner()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مالك.', 404);
        }

        return ApiResponse::success(
            new UserResource($owner),
            'بيانات المالك.'
        );
    }

    /**
     * Update the specified owner details.
     */
    public function update(UpdateOwnerRequest $request, User $owner): JsonResponse
    {
        if (! $owner->isOwner()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مالك.', 404);
        }

        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'national_id' => $validated['national_id'],
            'phone' => $validated['phone'] ?? null,
        ];

        if (array_key_exists('email', $validated)) {
            $updateData['email'] = $validated['email'];
        }

        if (! empty($validated['username'])) {
            $updateData['username'] = $validated['username'];
        }

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $owner->update($updateData);

        return ApiResponse::success(
            new UserResource($owner),
            'تم حفظ تعديلات المالك بنجاح.'
        );
    }

    /**
     * Toggle owner active status.
     */
    public function toggleStatus(User $owner): JsonResponse
    {
        if (! $owner->isOwner()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مالك.', 404);
        }

        $owner->update([
            'is_active' => ! $owner->is_active,
        ]);

        $message = $owner->is_active
            ? 'تم تفعيل حساب المالك بنجاح.'
            : 'تم إيقاف حساب المالك بنجاح.';

        return ApiResponse::success(
            new UserResource($owner),
            $message
        );
    }

    /**
     * Remove the specified owner.
     */
    public function destroy(User $owner): JsonResponse
    {
        if (! $owner->isOwner()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مالك.', 404);
        }

        if ($owner->ownedTenants()->exists()) {
            return ApiResponse::error('لا يمكن حذف المالك لوجود عقارات ومستأجرين مرتبطين به في النظام. يرجى إلغاء ربطهم أولاً.', 422);
        }

        $owner->delete();

        return ApiResponse::success(null, 'تم حذف حساب المالك بنجاح.');
    }
}
