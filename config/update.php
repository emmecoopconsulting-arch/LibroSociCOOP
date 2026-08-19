<?php

return [
    'current_version' => env('APP_VERSION', 'development'),
    'github_repository' => env('UPDATE_GITHUB_REPOSITORY', 'emmecoopconsulting-arch/librosocicoop'),
    'github_workflow' => env('UPDATE_GITHUB_WORKFLOW', 'docker-image.yml'),
    'github_branch' => env('UPDATE_GITHUB_BRANCH', 'main'),
    'github_token' => env('UPDATE_GITHUB_TOKEN'),
    'webhook_url' => env('UPDATE_WEBHOOK_URL'),
    'webhook_token' => env('UPDATE_WEBHOOK_TOKEN'),
    'verify_tls' => env('UPDATE_VERIFY_TLS', true),
];
