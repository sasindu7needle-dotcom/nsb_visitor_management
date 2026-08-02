<?php

namespace Tests\Feature;

use App\Models\ReturningFaceVerification;
use App\Models\VerifiedVisitor;
use App\Services\LocalFaceVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturningVisitorFaceVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::disk('local')->put('verified-visitors/registered.jpg', 'registered-face');

    }

    public function test_returning_visitor_is_found_by_nic_and_face_result_is_recorded(): void
    {
        $this->mock(LocalFaceVerificationService::class, function ($mock) {
            $mock->shouldReceive('compare')->once()->andReturn([
                'success' => true,
                'matched' => true,
                'similarity_percent' => 84.25,
                'live_detection_confidence' => 97.5,
            ]);
        });

        $visitor = VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'document_number' => '199012345678',
            'full_name' => 'Nimal Perera',
            'selfie_path' => 'verified-visitors/registered.jpg',
            'selfie_mime' => 'image/jpeg',
        ]);

        $this->postJson(route('visitor.returning.find'), ['nic_number' => '199012345678'])
            ->assertOk()
            ->assertJsonPath('visitor.name', 'Nimal Perera');

        $this->postJson(route('visitor.returning.compare'), [
            'selfie' => UploadedFile::fake()->image('return.jpg', 800, 600),
        ])->assertOk()
            ->assertJsonPath('status', 'same')
            ->assertJsonPath('match_score', '84.25')
            ->assertJsonPath('redirect_url', route('visitor.create', ['type' => 'nic']));

        $this->get(route('visitor.create', ['type' => 'nic']))
            ->assertOk()
            ->assertSee('Who are you visiting?')
            ->assertSee('Nimal Perera');

        $this->post(route('visitor.confirm'), [
            'mobile_number' => '771234567',
            'same_as_mobile' => '1',
            'department' => 'Finance Department',
            'person_to_meet' => 'Ms. Nirosha Fernando',
            'visitor_count' => 1,
        ])->assertOk()
            ->assertSee('sent to the security officer');

        $this->assertSame('pending', $visitor->fresh()->approval_status);
        $this->withSession(['admin_authenticated' => true])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Nimal Perera')
            ->assertSee('awaiting review');

        $this->assertDatabaseHas('returning_face_verifications', [
            'visitor_id' => $visitor->id,
            'nic_number' => '199012345678',
            'status' => 'same',
        ]);
        $path = $visitor->returningFaceVerifications()->value('photo_path');
        Storage::disk('local')->assertExists($path);
    }

    public function test_returning_visitor_page_is_available(): void
    {
        $this->get(route('visitor.returning'))
            ->assertOk()
            ->assertSee('NIC number')
            ->assertSee('Capture and compare');
    }

    public function test_returning_face_check_appears_on_the_admin_dashboard(): void
    {
        $visitor = VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'document_number' => '199012345678',
            'full_name' => 'Nimal Perera',
            'selfie_path' => 'verified-visitors/registered.jpg',
        ]);
        Storage::disk('local')->put('returning-face-checks/return.jpg', 'return-face');
        ReturningFaceVerification::create([
            'visitor_id' => $visitor->id,
            'nic_number' => '199012345678',
            'photo_path' => 'returning-face-checks/return.jpg',
            'photo_mime' => 'image/jpeg',
            'status' => 'different',
            'match_score' => 25.5,
            'checked_at' => now(),
        ]);

        $this->withSession(['admin_authenticated' => true])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Face Comparison Results')
            ->assertSee('Nimal Perera')
            ->assertSee('DIFFERENT');
    }
}
