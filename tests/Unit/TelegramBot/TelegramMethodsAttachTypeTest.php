<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramBot;

use App\TelegramBot\ParserMethods;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramMethodsAttachTypeTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config([
            'traffic_source.settings.telegram.token' => 'test_tg_token',
        ]);
    }

    public function test_send_photo_uses_photo_attach_type(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'fake image');

        $result = \App\TelegramBot\TelegramMethods::sendQueryTelegram('sendPhoto', [
            'chat_id' => 123,
            'uploaded_file_path' => $tempFile,
        ]);

        @unlink($tempFile);

        $this->assertTrue($result->ok);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'name="photo"');
        });
    }

    public function test_send_document_uses_document_attach_type(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 2]], 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'fake doc');

        $result = \App\TelegramBot\TelegramMethods::sendQueryTelegram('sendDocument', [
            'chat_id' => 123,
            'uploaded_file_path' => $tempFile,
        ]);

        @unlink($tempFile);

        $this->assertTrue($result->ok);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'name="document"');
        });
    }

    public function test_send_voice_uses_voice_attach_type(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 3]], 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'fake voice');

        $result = \App\TelegramBot\TelegramMethods::sendQueryTelegram('sendVoice', [
            'chat_id' => 123,
            'uploaded_file_path' => $tempFile,
        ]);

        @unlink($tempFile);

        $this->assertTrue($result->ok);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'name="voice"');
        });
    }

    public function test_uploaded_file_path_creates_uploaded_file(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 4]], 200),
        ]);

        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_');
        mkdir($tempDir);
        $tempFile = $tempDir . '/Ireland.ovpn';
        file_put_contents($tempFile, 'fake ovpn content');

        $result = \App\TelegramBot\TelegramMethods::sendQueryTelegram('sendDocument', [
            'chat_id' => 123,
            'uploaded_file_path' => $tempFile,
        ]);

        @unlink($tempFile);
        @rmdir($tempDir);

        $this->assertTrue($result->ok);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'Ireland.ovpn');
        });
    }

    public function test_send_message_without_file_uses_post(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 5, 'text' => 'Hello']], 200),
        ]);

        $result = \App\TelegramBot\TelegramMethods::sendQueryTelegram('sendMessage', [
            'chat_id' => 123,
            'text' => 'Hello',
        ]);

        $this->assertTrue($result->ok);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return !str_contains($body, 'name="document"')
                && !str_contains($body, 'name="photo"');
        });
    }

    public function test_attach_query_preserves_original_filename(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 6]], 200),
        ]);

        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_');
        mkdir($tempDir);
        $tempFile = $tempDir . '/myreport.pdf';
        file_put_contents($tempFile, 'fake pdf');

        $uploadedFile = new UploadedFile($tempFile, 'myreport.pdf', 'application/pdf', null, true);

        $result = ParserMethods::attachQuery(
            'https://api.telegram.org/bottest/sendDocument',
            ['chat_id' => 123, 'uploaded_file' => $uploadedFile],
            'document'
        );

        @unlink($tempFile);
        @rmdir($tempDir);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'myreport.pdf');
        });
    }
}
