<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AuthVerifyProfileAutofillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep tests portable when first_name/last_name columns are added manually in DB.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)->nullable();
            }
        });
    }

    public function test_case_1_verify_with_only_firebase_token_keeps_existing_flow(): void
    {
        $this->mockFirebase('firebase-uid-case-1', '+15555550111');

        $response = $this->postJson('/api/v1/auth/verify', [
            'firebase_token' => 'REAL_FIREBASE_TOKEN',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Authentication successful')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'firebase-uid-case-1',
            'phone' => '+15555550111',
            'first_name' => null,
            'last_name' => null,
            'email' => null,
        ]);
    }

    public function test_case_2_new_user_with_profile_fields_saves_profile_values(): void
    {
        $this->mockFirebase('firebase-uid-case-2', '+15555550112');

        $response = $this->postJson('/api/v1/auth/verify', [
            'firebase_token' => 'REAL_FIREBASE_TOKEN',
            'first_name' => 'Jay',
            'last_name' => 'Kanjariya',
            'email' => 'jay@gmail.com',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'firebase-uid-case-2',
            'first_name' => 'Jay',
            'last_name' => 'Kanjariya',
            'email' => 'jay@gmail.com',
            'name' => 'Jay Kanjariya',
        ]);
    }

    public function test_case_3_existing_user_with_blank_profile_gets_autofilled(): void
    {
        $user = User::factory()->create([
            'firebase_uid' => 'firebase-uid-case-3',
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'name' => null,
        ]);

        $this->mockFirebase('firebase-uid-case-3', $user->phone);

        $response = $this->postJson('/api/v1/auth/verify', [
            'firebase_token' => 'REAL_FIREBASE_TOKEN',
            'first_name' => 'Jay',
            'last_name' => 'Kanjariya',
            'email' => 'jay.autofill@gmail.com',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Jay',
            'last_name' => 'Kanjariya',
            'email' => 'jay.autofill@gmail.com',
            'name' => 'Jay Kanjariya',
        ]);
    }

    public function test_case_4_existing_user_with_profile_data_is_not_overwritten(): void
    {
        $user = User::factory()->create([
            'firebase_uid' => 'firebase-uid-case-4',
            'name' => 'Existing Name',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing.user@gmail.com',
        ]);

        $this->mockFirebase('firebase-uid-case-4', $user->phone);

        $response = $this->postJson('/api/v1/auth/verify', [
            'firebase_token' => 'REAL_FIREBASE_TOKEN',
            'first_name' => 'Jay',
            'last_name' => 'Kanjariya',
            'email' => 'jay@gmail.com',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Existing Name',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing.user@gmail.com',
        ]);
    }

    public function test_case_5_duplicate_email_on_another_account_returns_validation_error(): void
    {
        User::factory()->create([
            'firebase_uid' => 'existing-email-owner',
            'email' => 'duplicate@gmail.com',
        ]);

        User::factory()->create([
            'firebase_uid' => 'firebase-uid-case-5',
            'email' => null,
        ]);

        $this->mockFirebase('firebase-uid-case-5', '+15555550115');

        $response = $this->postJson('/api/v1/auth/verify', [
            'firebase_token' => 'REAL_FIREBASE_TOKEN',
            'email' => 'duplicate@gmail.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    private function mockFirebase(string $firebaseUid, ?string $phoneNumber): void
    {
        $mock = Mockery::mock(FirebaseService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->with('REAL_FIREBASE_TOKEN')
            ->andReturn((object) ['fake' => 'token']);
        $mock->shouldReceive('parseToken')
            ->once()
            ->andReturn([
                'firebase_uid' => $firebaseUid,
                'phone_number' => $phoneNumber,
            ]);

        $this->app->instance(FirebaseService::class, $mock);
    }
}
