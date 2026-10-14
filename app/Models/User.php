<?php

namespace App\Models;

use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'phone', 'national_id', 'type', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if the user is a general manager (مشرف عام).
     */
    public function isGeneralManager(): bool
    {
        return $this->type === UserType::GeneralManager;
    }

    /**
     * Determine if the user is an owner (مالك).
     */
    public function isOwner(): bool
    {
        return $this->type === UserType::Owner;
    }

    /**
     * Determine if the user is a real estate supervisor (مشرف عقارات).
     */
    public function isRealEstateSupervisor(): bool
    {
        return $this->type === UserType::RealEstateSupervisor;
    }

    /**
     * Determine if the user is a collection supervisor (مشرف تحصيل ومتابعة).
     */
    public function isCollectionSupervisor(): bool
    {
        return $this->type === UserType::CollectionSupervisor;
    }

    /**
     * Determine if the user is a collector (محصل).
     */
    public function isCollector(): bool
    {
        return $this->type === UserType::Collector;
    }

    /**
     * Determine if the user is a maintenance supervisor (مشرف صيانه).
     */
    public function isMaintenanceSupervisor(): bool
    {
        return $this->type === UserType::MaintenanceSupervisor;
    }

    /**
     * Determine if the user belongs to any supervisor category.
     */
    public function isSupervisorType(): bool
    {
        return $this->type?->isSupervisorType() ?? false;
    }

    /**
     * Determine if the user is a tenant (مستأجر).
     */
    public function isTenant(): bool
    {
        return $this->type === UserType::Tenant;
    }

    /**
     * The tenant profile/info if the user is a tenant.
     */
    public function tenantInfo(): HasOne
    {
        return $this->hasOne(TenantInfo::class, 'user_id');
    }

    /**
     * Notice requests initiated by this user (collector).
     */
    public function noticeRequests(): HasMany
    {
        return $this->hasMany(NoticeRequest::class, 'collector_id');
    }

    /**
     * Tenants assigned to this collector.
     */
    public function collectedTenants(): HasMany
    {
        return $this->hasMany(TenantInfo::class, 'collector_id');
    }

    /**
     * Payments recorded by this collector.
     */
    public function collectorPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'collector_id');
    }

    /**
     * Tenants/properties owned by this owner.
     */
    public function ownedTenants(): HasMany
    {
        return $this->hasMany(TenantInfo::class, 'owner_id');
    }
}
