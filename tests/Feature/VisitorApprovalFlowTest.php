<?php

namespace Tests\Feature;

use App\Models\VerifiedVisitor;
use App\Services\GateLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_details_create_a_security_alert_that_must_be_allowed_before_gate_entry(): void
    {
        $verification = [
            'session_id' => '12345678-1234-4234-8234-123456789012',
            'verification_id' => '12345678-1234-4234-8234-123456789012',
            'document_type' => 'nic',
            'full_name' => 'Rahul Perera',
            'document_number' => '123456789V',
            'address' => 'Colombo',
            'selfie_path' => 'verified-visitors/rahul-face.jpg',
            'selfie_mime' => 'image/jpeg',
        ];

        $this->withSession(['verification' => $verification])
            ->post(route('visitor.confirm'), [
                'document_type' => 'nic',
                'full_name' => 'Rahul Perera',
                'document_number' => '123456789V',
                'address' => 'Colombo',
                'mobile_number' => '771234567',
                'same_as_mobile' => '1',
                'department' => 'Finance Department',
                'person_to_meet' => 'Ms. Nirosha Fernando',
                'visitor_count' => 2,
                'purpose' => 'Account review meeting',
            ])
            ->assertOk()
            ->assertSee('sent to the security officer')
            ->assertDontSee('Choose a payment method');

        $visitor = VerifiedVisitor::firstOrFail();
        $this->assertSame('pending', $visitor->approval_status);
        $this->assertSame('Finance Department', $visitor->department);
        $this->assertSame('Ms. Nirosha Fernando', $visitor->person_to_meet);
        $this->assertSame(2, $visitor->visitor_count);
        $this->assertSame('+94771234567', $visitor->mobile_number);
        $this->assertSame('not_required', $visitor->payment_status);
        $this->assertSame('approval_pending', $visitor->registration_status);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Security Officer — New Visitor Alert')
            ->assertSee('Rahul Perera')
            ->assertSee('Finance Department')
            ->assertSee('Ms. Nirosha Fernando')
            ->assertSee('Main Gate')
            ->assertSee('Visitor pass ID')
            ->assertSee('Pass handed to visitor');

        try {
            app(GateLogService::class)->preview($visitor->verification_id, 'in');
            $this->fail('A pending visit request must not be admitted.');
        } catch (\App\Exceptions\GateScanException $exception) {
            $this->assertSame('approval_required', $exception->reason);
        }

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->patch(route('admin.dashboard.visitor_requests.decide', $visitor), [
                'decision' => 'allow',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('approved', $visitor->fresh()->approval_status);
        $this->assertFalse($visitor->fresh()->is_blocked);
        $this->assertTrue($visitor->fresh()->checkin_status);
        $this->assertSame('checked_in', $visitor->fresh()->registration_status);
        $this->assertDatabaseHas('gate_logs', [
            'visitor_id' => $visitor->id,
            'gate' => 'ADMIN',
            'direction' => 'in',
        ]);
    }

    public function test_security_can_record_a_visitor_pass_as_issued_and_returned(): void
    {
        $visitor = VerifiedVisitor::create([
            'verification_id' => '32345678-1234-4234-8234-123456789012',
            'full_name' => 'Pass Holder',
            'approval_status' => 'pending',
            'registration_status' => 'approval_pending',
            'payment_status' => 'not_required',
        ]);
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->patch(route('admin.dashboard.visitor_requests.decide', $visitor), [
                'decision' => 'allow',
                'pass_issued' => '1',
                'visitor_pass_number' => 'VP-014',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('verified_visitors', [
            'id' => $visitor->id,
            'approval_status' => 'approved',
            'visitor_pass_number' => 'VP-014',
        ]);
        $this->assertNotNull($visitor->fresh()->visitor_pass_issued_at);

        app(GateLogService::class)->scan($visitor->verification_id, 'A', null, 'out');
        $this->assertFalse($visitor->fresh()->checkin_status);

        $this->withSession($session)
            ->get(route('admin.visitors.index'))
            ->assertOk()
            ->assertSee('Visitor pass returned (VP-014)')
            ->assertDontSee('Check In Visitor')
            ->assertDontSee('Check Out Visitor');

        $this->withSession($session)
            ->patch(route('admin.dashboard.visitor_passes.return', $visitor))
            ->assertRedirect();

        $this->assertNotNull($visitor->fresh()->visitor_pass_returned_at);
    }

    public function test_live_face_registration_screen_is_no_longer_available(): void
    {
        $this->get('/visitor/live-face-check')->assertMethodNotAllowed();
    }
}
