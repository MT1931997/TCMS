<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_roles_list(): void
    {
        $response = $this->getJson(route('api.auth.roles'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['value', 'label'],
                ],
            ]);
    }

    public function test_can_fetch_users_by_type(): void
    {
        User::factory()->create([
            'name' => 'مشرف تجريبي',
            'type' => UserType::Supervisor,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.auth.users-by-type', ['type' => 'supervisor']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'مشرف تجريبي');
    }

    public function test_user_can_login_with_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'collector_user',
            'password' => Hash::make('secret123'),
            'type' => UserType::Collector,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'login' => 'collector_user',
            'password' => 'secret123',
            'type' => 'collector',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.type', 'collector')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'username', 'email', 'phone', 'type', 'type_label'],
                ],
            ]);
    }

    public function test_user_can_login_with_user_id(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
            'type' => UserType::Supervisor,
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'user_id' => $user->id,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'username' => 'valid_user',
            'password' => Hash::make('correct_pass'),
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'login' => 'valid_user',
            'password' => 'wrong_pass',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'كلمة المرور غير صحيحة.');
    }

    public function test_login_fails_when_user_type_does_not_match(): void
    {
        User::factory()->create([
            'username' => 'tenant_user',
            'password' => Hash::make('secret123'),
            'type' => UserType::Tenant,
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'login' => 'tenant_user',
            'password' => 'secret123',
            'type' => 'supervisor',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'هذا الحساب غير مسجل كـ مشرف.');
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create([
            'type' => UserType::Owner,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.auth.me'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.type', 'owner');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.auth.logout'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم تسجيل الخروج بنجاح.');

        $this->assertDatabaseEmpty('personal_access_tokens');
    }
}
