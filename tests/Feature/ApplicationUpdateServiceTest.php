<?php

namespace Tests\Feature;

use App\Services\ApplicationUpdateService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApplicationUpdateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('APP_VERSION=docker-test');
        config()->set([
            'update.current_version' => str_repeat('a', 40),
            'update.github_repository' => 'owner/repository',
            'update.github_workflow' => 'docker-image.yml',
            'update.github_branch' => 'main',
            'update.webhook_url' => 'https://portainer.test/api/stacks/webhooks/example',
            'update.webhook_token' => null,
            'update.verify_tls' => true,
        ]);
    }

    protected function tearDown(): void
    {
        putenv('APP_VERSION');

        parent::tearDown();
    }

    public function test_it_detects_a_new_successfully_published_docker_image(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'workflow_runs' => [['head_sha' => str_repeat('b', 40)]],
            ]),
        ]);

        $status = app(ApplicationUpdateService::class)->check();

        $this->assertTrue($status['available']);
        $this->assertTrue($status['can_update']);
        $this->assertSame('aaaaaaa', $status['current']);
        $this->assertSame('bbbbbbb', $status['remote']);
    }

    public function test_it_triggers_the_configured_update_webhook(): void
    {
        Http::fake([
            'portainer.test/*' => Http::response(status: 204),
        ]);

        $log = app(ApplicationUpdateService::class)->update();

        $this->assertSame('Aggiornamento Docker', $log[0]['command']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://portainer.test/api/stacks/webhooks/example');
    }
}
