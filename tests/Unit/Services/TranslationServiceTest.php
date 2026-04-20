<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TranslationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslationServiceTest extends TestCase
{
    private TranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TranslationService();
    }

    public function test_should_translate_returns_false_when_disabled(): void
    {
        config(['translation.enabled' => false]);

        $this->assertFalse($this->service->shouldTranslate('שלום'));
    }

    public function test_should_translate_returns_false_when_no_api_key(): void
    {
        config(['translation.enabled' => true, 'translation.deepl_api_key' => '']);

        $this->assertFalse($this->service->shouldTranslate('שלום'));
    }

    public function test_should_translate_returns_false_for_non_hebrew_text(): void
    {
        config([
            'translation.enabled' => true,
            'translation.deepl_api_key' => 'test-key',
            'translation.source_language' => 'HE',
        ]);

        $this->assertFalse($this->service->shouldTranslate('Hello world'));
        $this->assertFalse($this->service->shouldTranslate('Привет мир'));
    }

    public function test_should_translate_returns_true_for_hebrew_text(): void
    {
        config([
            'translation.enabled' => true,
            'translation.deepl_api_key' => 'test-key',
            'translation.source_language' => 'HE',
        ]);

        $this->assertTrue($this->service->shouldTranslate('שלום'));
        $this->assertTrue($this->service->shouldTranslate('Hello שלום mixed'));
    }

    public function test_maybe_translate_returns_original_when_disabled(): void
    {
        config(['translation.enabled' => false]);

        $text = 'שלום';
        $this->assertSame($text, $this->service->maybeTranslate($text));
    }

    public function test_maybe_translate_appends_translation_on_success(): void
    {
        config([
            'translation.enabled' => true,
            'translation.deepl_api_key' => 'test-key',
            'translation.deepl_api_url' => 'https://api-free.deepl.com/v2',
            'translation.source_language' => 'HE',
            'translation.target_language' => 'RU',
            'translation.append_label' => '🌐 Перевод (HE→RU):',
        ]);

        Http::fake([
            'api-free.deepl.com/v2/translate' => Http::response([
                'translations' => [
                    ['text' => 'Привет'],
                ],
            ], 200),
        ]);

        $result = $this->service->maybeTranslate('שלום');

        $this->assertStringContainsString('שלום', $result);
        $this->assertStringContainsString('🌐 Перевод (HE→RU):', $result);
        $this->assertStringContainsString('Привет', $result);
    }

    public function test_maybe_translate_returns_original_when_deepl_fails(): void
    {
        config([
            'translation.enabled' => true,
            'translation.deepl_api_key' => 'test-key',
            'translation.deepl_api_url' => 'https://api-free.deepl.com/v2',
            'translation.source_language' => 'HE',
            'translation.target_language' => 'RU',
        ]);

        Http::fake([
            'api-free.deepl.com/v2/translate' => Http::response(['error' => 'Unauthorized'], 403),
        ]);

        $text = 'שלום';
        $this->assertSame($text, $this->service->maybeTranslate($text));
    }

    public function test_deepl_receives_correct_auth_header_and_params(): void
    {
        config([
            'translation.enabled' => true,
            'translation.deepl_api_key' => 'my-secret-key',
            'translation.deepl_api_url' => 'https://api-free.deepl.com/v2',
            'translation.source_language' => 'HE',
            'translation.target_language' => 'RU',
            'translation.append_label' => '🌐 Перевод:',
        ]);

        Http::fake([
            'api-free.deepl.com/v2/translate' => Http::response([
                'translations' => [['text' => 'Мир']],
            ], 200),
        ]);

        $this->service->maybeTranslate('עולם');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'DeepL-Auth-Key my-secret-key')
                && $request->url() === 'https://api-free.deepl.com/v2/translate'
                && $request['source_lang'] === 'HE'
                && $request['target_lang'] === 'RU';
        });
    }
}
