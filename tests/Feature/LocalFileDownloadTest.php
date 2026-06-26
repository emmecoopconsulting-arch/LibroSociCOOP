<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocalFileDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_download_allowed_local_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rapporti-interventi/rapportino.pdf', 'PDF');

        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $this->actingAs($admin)
            ->get(route('local-files.file', [
                'path' => Crypt::encryptString('rapporti-interventi/rapportino.pdf'),
                'download' => true,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_local_file_download_rejects_disallowed_paths(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private.txt', 'secret');

        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $this->actingAs($admin)
            ->get(route('local-files.file', [
                'path' => Crypt::encryptString('private.txt'),
            ]))
            ->assertNotFound();
    }
}
