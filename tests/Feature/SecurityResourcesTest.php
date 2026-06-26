<?php

namespace Tests\Feature;

use App\Filament\Pages\Impostazioni;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_resources_are_available_to_administrators(): void
    {
        $admin = $this->userWithRole('amministratore');

        $this->actingAs($admin);

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(AuditLogResource::canViewAny());
        $this->assertTrue(Impostazioni::canAccess());

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/audit-logs')->assertOk();
        $this->get('/admin/impostazioni')->assertOk();
    }

    public function test_security_resources_are_hidden_from_operators(): void
    {
        $operator = $this->userWithRole('operatore');

        $this->actingAs($operator);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(AuditLogResource::canViewAny());
        $this->assertFalse(Impostazioni::canAccess());

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/audit-logs')->assertForbidden();
        $this->get('/admin/impostazioni')->assertForbidden();
    }

    public function test_user_changes_are_written_to_audit_log(): void
    {
        $admin = $this->userWithRole('amministratore');
        $user = $this->userWithRole('operatore');

        $this->actingAs($admin);

        $user->update([
            'email' => 'operatore.aggiornato@example.com',
        ]);

        $this->assertDatabaseHas(Activity::class, [
            'log_name' => 'sicurezza',
            'event' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => User::class,
            'causer_id' => $admin->id,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        Role::findOrCreate('amministratore');
        Role::findOrCreate('operatore');

        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }
}
