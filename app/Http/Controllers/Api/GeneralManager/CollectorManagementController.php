<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\StoreCollectorRequest;
use App\Http\Requests\GeneralManager\UpdateCollectorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CollectorManagementController extends Controller
{
    /**
     * Display a listing of all collectors with search and status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('type', UserType::Collector->value);

        // Search by name, phone, username, or email
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status (active / inactive)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $allCollectorsCount = User::where('type', UserType::Collector->value)->count();
        $activeCount = User::where('type', UserType::Collector->value)->where('is_active', true)->count();
        $inactiveCount = User::where('type', UserType::Collector->value)->where('is_active', false)->count();

        $statistics = [
            'all_count' => $allCollectorsCount,
            'active_count' => $activeCount,
            'inactive_count' => $inactiveCount,
        ];

        $tabsCounts = [
            'supervisors' => User::whereIn('type', array_map(fn (UserType $t): string => $t->value, UserType::supervisorTypes()))->count(),
            'collectors' => $allCollectorsCount,
            'owners' => User::where('type', UserType::Owner->value)->count(),
        ];

        if ($perPage = $request->query('per_page')) {
            $collectors = $query->latest('id')->paginate((int) $perPage);

            return ApiResponse::success([
                'collectors' => UserResource::collection($collectors),
                'pagination' => [
                    'total' => $collectors->total(),
                    'per_page' => $collectors->perPage(),
                    'current_page' => $collectors->currentPage(),
                    'last_page' => $collectors->lastPage(),
                ],
                'statistics' => $statistics,
                'tabs_counts' => $tabsCounts,
            ], 'تم جلب قائمة المحصلين بنجاح.');
        }

        $collectors = $query->latest('id')->get();

        return ApiResponse::success([
            'collectors' => UserResource::collection($collectors),
            'statistics' => $statistics,
            'tabs_counts' => $tabsCounts,
        ], 'تم جلب قائمة المحصلين بنجاح.');
    }

    /**
     * Store a newly created collector.
     */
    public function store(StoreCollectorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $username = $validated['username'] ?? null;
        if (empty($username)) {
            $cleanName = Str::slug($validated['name'], '_');
            $baseUsername = ! empty($cleanName) ? $cleanName : 'collector';
            $username = $baseUsername.'_'.Str::lower(Str::random(5));

            while (User::where('username', $username)->exists()) {
                $username = $baseUsername.'_'.Str::lower(Str::random(6));
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'type' => UserType::Collector,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return ApiResponse::success(
            new UserResource($user),
            'تمت إضافة المحصل بنجاح.',
            201
        );
    }

    /**
     * Display the specified collector details.
     */
    public function show(User $collector): JsonResponse
    {
        if (! $collector->isCollector()) {
            return ApiResponse::error('هذا المستخدم ليس حساب محصل.', 404);
        }

        return ApiResponse::success(
            new UserResource($collector),
            'بيانات المحصل.'
        );
    }

    /**
     * Update the specified collector details.
     */
    public function update(UpdateCollectorRequest $request, User $collector): JsonResponse
    {
        if (! $collector->isCollector()) {
            return ApiResponse::error('هذا المستخدم ليس حساب محصل.', 404);
        }

        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
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

        $collector->update($updateData);

        return ApiResponse::success(
            new UserResource($collector),
            'تم حفظ تعديلات المحصل بنجاح.'
        );
    }

    /**
     * Toggle collector active status.
     */
    public function toggleStatus(User $collector): JsonResponse
    {
        if (! $collector->isCollector()) {
            return ApiResponse::error('هذا المستخدم ليس حساب محصل.', 404);
        }

        $collector->update([
            'is_active' => ! $collector->is_active,
        ]);

        $message = $collector->is_active
            ? 'تم تفعيل حساب المحصل بنجاح.'
            : 'تم إيقاف حساب المحصل بنجاح.';

        return ApiResponse::success(
            new UserResource($collector),
            $message
        );
    }

    /**
     * Remove the specified collector.
     */
    public function destroy(User $collector): JsonResponse
    {
        if (! $collector->isCollector()) {
            return ApiResponse::error('هذا المستخدم ليس حساب محصل.', 404);
        }

        if ($collector->collectedTenants()->exists()) {
            return ApiResponse::error('لا يمكن حذف المحصل لوجود مستأجرين مرتبطين به في النظام. يرجى إعادة تعيينهم أولاً.', 422);
        }

        $collector->delete();

        return ApiResponse::success(null, 'تم حذف حساب المحصل بنجاح.');
    }
}
