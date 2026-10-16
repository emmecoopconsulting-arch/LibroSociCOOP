<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BedrockPayrollOcrService
{
    private const OCR_PROMPT = 'Trascrivi fedelmente tutto il testo leggibile in questa pagina di busta paga. Mantieni righe, importi, date, nome e codice fiscale. Non correggere, completare o inventare caratteri incerti: riportali come appaiono. Restituisci solo il testo trascritto.';

    public function recognize(string $imagePath): string
    {
        $image = file_get_contents($imagePath);

        if ($image === false) {
            throw new RuntimeException('Impossibile leggere l’immagine della pagina da inviare a Bedrock.');
        }

        return $this->converse([
            ['text' => self::OCR_PROMPT],
            [
                'image' => [
                    'format' => 'jpeg',
                    'source' => ['bytes' => base64_encode($image)],
                ],
            ],
        ], 4096);
    }

    public function testConnection(): void
    {
        $text = $this->converse([
            ['text' => 'Rispondi soltanto con OK.'],
        ], 16);

        if (strtoupper(trim($text)) !== 'OK') {
            throw new RuntimeException('Bedrock ha risposto, ma la risposta del test non è quella attesa.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     */
    private function converse(array $content, int $maxTokens): string
    {
        $apiKey = AppSetting::bedrockOcrApiKey();
        $region = AppSetting::string(AppSetting::BEDROCK_REGION) ?: 'us-east-1';
        $modelId = AppSetting::string(AppSetting::BEDROCK_MODEL_ID);

        if (blank($apiKey)) {
            throw new RuntimeException('Inserisci la chiave API di Amazon Bedrock nelle Impostazioni.');
        }

        if (! preg_match('/\A[a-z]{2}(?:-gov)?-[a-z]+-\d\z/', $region)) {
            throw new RuntimeException('La regione AWS configurata non è valida.');
        }

        if (blank($modelId) || ! preg_match('/\A[A-Za-z0-9.:\/_-]{1,2048}\z/', $modelId)) {
            throw new RuntimeException('Inserisci un ID o ARN valido del profilo di inferenza Bedrock.');
        }

        $url = 'https://bedrock-runtime.'.$region.'.amazonaws.com/model/'.rawurlencode($modelId).'/converse';
        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->timeout(120)
            ->post($url, [
                'messages' => [[
                    'role' => 'user',
                    'content' => $content,
                ]],
                'inferenceConfig' => [
                    'maxTokens' => $maxTokens,
                    'temperature' => 0,
                ],
            ]);

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->json('Message')
                ?? 'Verifica regione, profilo, permessi IAM e scadenza della chiave.';
            throw new RuntimeException('Amazon Bedrock ha restituito HTTP '.$response->status().': '.$message);
        }

        $text = collect($response->json('output.message.content', []))
            ->pluck('text')
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->implode("\n");

        if (blank($text)) {
            throw new RuntimeException('Amazon Bedrock non ha restituito testo OCR.');
        }

        return trim($text);
    }
}
