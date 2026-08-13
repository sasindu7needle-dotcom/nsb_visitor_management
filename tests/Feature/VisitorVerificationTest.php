<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VisitorVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('services.gemini.api_key', 'test-gemini-key');
    }

    public function test_gemini_reads_document_information_then_sends_visitor_to_photo_capture(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'full_name' => 'Nimal Perera',
                            'document_number' => '199012345678',
                            'address' => '12 Galle Road, Colombo',
                            'confidence' => 98,
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $response = $this->postJson(route('visitor.read_document'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 600, 400),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 600, 400),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Nimal Perera')
            ->assertJsonPath('data.document_number', '199012345678')
            ->assertJsonPath('redirect_url', route('visitor.capture_photo'));

        $this->assertSame('Nimal Perera', session('verification.full_name'));
        $this->assertSame('google_gemini', session('verification.provider'));
        $this->assertNull(session('verification.face_verification_status'));
    }

    public function test_photo_capture_is_saved_against_the_current_visitor_registration(): void
    {
        $registrationId = '11111111-2222-4333-8444-555555555555';
        $response = $this->withSession(['verification' => [
            'session_id' => $registrationId,
            'verification_id' => $registrationId,
            'document_type' => 'nic',
        ]])->postJson(route('visitor.capture_photo.store'), [
            'selfie' => UploadedFile::fake()->image('visitor-face.jpg', 800, 600),
        ]);

        $response->assertOk()
            ->assertJsonPath('redirect_url', route('visitor.create', ['type' => 'nic']));
        $this->assertSame("verified-visitors/{$registrationId}-face.jpg", session('verification.selfie_path'));
        Storage::disk('local')->assertExists("verified-visitors/{$registrationId}-face.jpg");
    }

    public function test_document_reader_requires_gemini_configuration(): void
    {
        config()->set('services.gemini.api_key', '');

        $this->postJson(route('visitor.read_document'), [
            'document_type' => 'passport',
            'document_front_image' => UploadedFile::fake()->image('passport.jpg', 600, 400),
        ])->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_live_face_registration_routes_are_not_available(): void
    {
        $this->get('/visitor/live-face-check')->assertMethodNotAllowed();
        $this->postJson('/api/visitor/verify-live-face')->assertNotFound();
    }
}
