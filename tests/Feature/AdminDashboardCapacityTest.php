<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Tests\TestCase;

class AdminDashboardCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_offer_manual_occupancy_controls(): void
    {
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('EVENT OCCUPANCY CONTROL')
            ->assertDontSee('Capacity is not configured')
            ->assertDontSee('Visitors Inside')
            ->assertDontSee('Exhibitors Inside')
            ->assertDontSee('Staff Inside');

        $this->assertFalse(Route::has('admin.dashboard.inside_count'));
    }

    public function test_dashboard_statistic_cards_expose_the_matching_visitor_details(): void
    {
        $visitor = VerifiedVisitor::create([
            'verification_id' => 'f0e1d2c3-b4a5-4678-9012-123456789012',
            'full_name' => 'Inside Detail Visitor',
            'document_number' => '991234567V',
            'verified_at' => now(),
            'checked_in_at' => now(),
            'checkin_status' => true,
            'approval_status' => 'approved',
        ]);
        GateLog::create([
            'visitor_id' => $visitor->id,
            'gate' => 'ADMIN',
            'direction' => 'in',
            'scanned_at' => now(),
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-stat-details="inside"', false)
            ->assertSee('Visitors Currently Inside')
            ->assertSee('Inside Detail Visitor')
            ->assertSee('991234567V')
            ->assertSee(route('admin.visitors.checkout', $visitor), false)
            ->assertSee('Check out')
            ->assertSee('data-dashboard-profile="'.$visitor->id.'"', false)
            ->assertSee('COMPLETE VISIT HISTORY')
            ->assertSee('Inside now');

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->from(route('admin.dashboard'))
            ->patch(route('admin.visitors.checkout', $visitor))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($visitor->fresh()->checkin_status);
        $this->assertDatabaseHas('gate_logs', [
            'visitor_id' => $visitor->id,
            'gate' => 'ADMIN',
            'direction' => 'out',
        ]);
    }
}
