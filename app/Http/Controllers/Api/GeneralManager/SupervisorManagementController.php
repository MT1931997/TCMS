<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\StoreSupervisorRequest;
use App\Http\Requests\GeneralManager\UpdateSupervisorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SupervisorManagementController extends Controller
{
    /**
     * Display a listing of all supervisors with search and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $supervisorTypeValues = array_map(
            fn (UserType $t): string => $t->value,
            UserType::supervisorTypes()
        );

        $query = User::query()->whereIn('type', $supervisorTypeValues);

        // Search by name, username, phone, or email
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by specific supervisor type
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Filter by status (active / inactive)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Statistics for summary cards
        $statistics = [
            'all_count' => User::whereIn('type', $supervisorTypeValues)->count(),
            'general_manager_count' => User::where('type', UserType::GeneralManager->value)->count(),
            'real_estate_count' => User::where('type', UserType::RealEstateSupervisor->value)->count(),
            'collection_count' => User::where('type', UserType::CollectionSupervisor->value)->count(),
            'maintenance_count' => User::where('type', UserType::MaintenanceSupervisor->value)->count(),
            'active_count' => User::whereIn('type', $supervisorTypeValues)->where('is_active', true)->count(),
            'inactive_count' => User::whereIn('type', $supervisorTypeValues)->where('is_active', false)->count(),
        ];

        // Optional pagination or full list
        if ($perPage = $request->query('per_page')) {
            $supervisors = $query->latest('id')->paginate((int) $perPage);

            return ApiResponse::success([
                'supervisors' => UserResource::collection($supervisors),
                'pagination' => [
                    'total' => $supervisors->total(),
                    'per_page' => $supervisors->perPage(),
                    'current_page' => $supervisors->currentPage(),
                    'last_page' => $supervisors->lastPage(),
                ],
                'statistics' => $statistics,
                'types' => UserType::supervisorOptions(),
            ], 'تم جلب قائمة المشرفين بنجاح.');
        }

        $supervisors = $query->latest('id')->get();

        return ApiResponse::success([
            'supervisors' => UserResource::collection($supervisors),
            'statistics' => $statistics,
            'types' => UserType::supervisorOptions(),
        ], 'تم جلب قائمة المشرفين بنجاح.');
    }

    /**
     * Store a newly created supervisor account.
     */
    public function store(StoreSupervisorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Generate username if not provided
        $username = $validated['username'] ?? null;
        if (empty($username)) {
            $cleanName = Str::slug($validated['name'], '_');
            $baseUsername = ! empty($cleanName) ? $cleanName : 'supervisor';
            $username = $baseUsername.'_'.Str::lower(Str::random(5));

            while (User::where('username', $username)->exists()) {
                $username = $baseUsername.'_'.Str::lower(Str::random(6));
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'type' => $validated['type'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return ApiResponse::success(
            new UserResource($user),
            'تمت إضافة المشرف بنجاح.',
            201
        );
    }

    /**
     * Display the specified supervisor details.
     */
    public function show(User $supervisor): JsonResponse
    {
        if (! $supervisor->isSupervisorType()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مشرف.', 404);
        }

        return ApiResponse::success(
            new UserResource($supervisor),
            'بيانات المشرف.'
        );
    }

    /**
     * Update the specified supervisor details and/or password.
     */
    public function update(UpdateSupervisorRequest $request, User $supervisor): JsonResponse
    {
        if (! $supervisor->isSupervisorType()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مشرف.', 404);
        }

        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'phone' => $validated['phone'],
        ];

        if (array_key_exists('email', $validated)) {
            $updateData['email'] = $validated['email'];
        }

        if (! empty($validated['username'])) {
            $updateData['username'] = $validated['username'];
        }

        // Only update password if a new one was provided
        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $supervisor->update($updateData);

        return ApiResponse::success(
            new UserResource($supervisor),
            'تم حفظ تعديلات المشرف بنجاح.'
        );
    }

    /**
     * Toggle active/inactive status (إيقاف الحساب / تفعيل الحساب).
     */
    public function toggleStatus(Request $request, User $supervisor): JsonResponse
    {
        if (! $supervisor->isSupervisorType()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مشرف.', 404);
        }

        // Prevent suspending own account
        if ($supervisor->id === $request->user()->id) {
            return ApiResponse::error('لا يمكنك إيقاف أو حذف حسابك أنت.', 422);
        }

        $supervisor->update([
            'is_active' => ! $supervisor->is_active,
        ]);

        $message = $supervisor->is_active
            ? 'تم تفعيل حساب المشرف بنجاح.'
            : 'تم إيقاف حساب المشرف بنجاح.';

        return ApiResponse::success(
            new UserResource($supervisor),
            $message
        );
    }

    /**
     * Remove the specified supervisor from the system.
     */
    public function destroy(Request $request, User $supervisor): JsonResponse
    {
        if (! $supervisor->isSupervisorType()) {
            return ApiResponse::error('هذا المستخدم ليس حساب مشرف.', 404);
        }

        // Prevent deleting own account
        if ($supervisor->id === $request->user()->id) {
            return ApiResponse::error('لا يمكنك إيقاف أو حذف حسابك أنت.', 422);
        }

        $supervisor->delete();

        return ApiResponse::success(null, 'تم حذف حساب المشرف بنجاح.');
    }
}
