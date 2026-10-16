<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\BedrockPayrollOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BedrockPayrollOcrTest extends TestCase
{
    use RefreshDatabase;

    public function test_bedrock_ocr_sends_page_image_with_bearer_key_and_returns_transcription(): void
    {
        AppSetting::setValue(AppSetting::BEDROCK_REGION, 'us-east-1');
        AppSetting::setValue(AppSetting::BEDROCK_MODEL_ID, 'us.amazon.nova-lite-v1:0');
        AppSetting::setBedrockOcrApiKey('test-bedrock-key');

        $imagePath = tempnam(sys_get_temp_dir(), 'payroll-ocr-');
        file_put_contents($imagePath, 'fake-jpeg-bytes');

        Http::fake([
            'https://bedrock-runtime.us-east-1.amazonaws.com/*' => Http::response([
                'output' => [
                    'message' => [
                        'content' => [['text' => 'COPELLI MATTEO CPMLMT00S09A271L']],
                    ],
                ],
            ]),
        ]);

        try {
            $text = app(BedrockPayrollOcrService::class)->recognize($imagePath);
        } finally {
            @unlink($imagePath);
        }

        $this->assertSame('COPELLI MATTEO CPMLMT00S09A271L', $text);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-bedrock-key')
            && data_get($request->data(), 'inferenceConfig.maxTokens') === 4096
            && data_get($request->data(), 'messages.0.content.1.image.source.bytes') === base64_encode('fake-jpeg-bytes'));
    }

    public function test_bedrock_api_key_is_encrypted_at_rest_and_can_be_decrypted_for_requests(): void
    {
        AppSetting::setBedrockOcrApiKey('test-bedrock-key');

        $stored = AppSetting::query()->where('key', AppSetting::BEDROCK_API_KEY)->value('value');

        $this->assertNotSame('test-bedrock-key', $stored);
        $this->assertSame('test-bedrock-key', AppSetting::bedrockOcrApiKey());
    }
}
