<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentPerson;
use App\Models\VisitorAppointment;
use App\Mail\AppointmentRegistrationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_appointment_schedule(): void
    {
        Department::create(['name' => 'Customer Service']);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('Schedule a visit')
            ->assertSee('Customer Service');
    }

    public function test_appointments_navigation_is_visible_from_the_visitor_screen(): void
    {
        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.visitors.index'))
            ->assertOk()
            ->assertSee('>Appointments<', false);
    }

    public function test_admin_can_create_an_appointment_for_an_available_department_host(): void
    {
        Mail::fake();

        $department = Department::create(['name' => 'Customer Service']);
        $person = DepartmentPerson::create([
            'department_id' => $department->id,
            'name' => 'Ms. Jane Perera',
            'designation' => 'Manager',
        ]);

        $response = $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->post(route('admin.appointments.store'), [
                'visitor_name' => 'Nimal Perera',
                'phone' => '0771234567',
                'email' => 'nimal@example.com',
                'company' => 'Example Ltd',
                'department_id' => $department->id,
                'department_person_id' => $person->id,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'duration_minutes' => 30,
                'notes' => 'Visitor will arrive by car.',
            ]);

        $response->assertRedirect(route('admin.appointments.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('visitor_appointments', [
            'visitor_name' => 'Nimal Perera',
            'department_id' => $department->id,
            'department_person_id' => $person->id,
            'status' => 'scheduled',
        ]);
        Mail::assertSent(AppointmentRegistrationMail::class, function (AppointmentRegistrationMail $mail) {
            return $mail->appointment->email === 'nimal@example.com';
        });
    }

    public function test_admin_cannot_assign_a_host_from_another_department(): void
    {
        $department = Department::create(['name' => 'Finance']);
        $otherDepartment = Department::create(['name' => 'Legal Services']);
        $otherPerson = DepartmentPerson::create([
            'department_id' => $otherDepartment->id,
            'name' => 'Ms. Other Host',
        ]);

        $response = $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->from(route('admin.appointments.index'))
            ->post(route('admin.appointments.store'), [
                'visitor_name' => 'Nimal Perera',
                'phone' => '0771234567',
                'email' => 'nimal@example.com',
                'department_id' => $department->id,
                'department_person_id' => $otherPerson->id,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'duration_minutes' => 30,
            ]);

        $response->assertRedirect(route('admin.appointments.index'));
        $response->assertSessionHasErrors('department_person_id');
        $this->assertDatabaseCount('visitor_appointments', 0);
    }

    public function test_admin_can_mark_an_appointment_completed(): void
    {
        $department = Department::create(['name' => 'Operations']);
        $appointment = VisitorAppointment::create([
            'reference' => 'APT-260803-ABCDE',
            'visitor_name' => 'Nimal Perera',
            'phone' => '0771234567',
            'visitor_count' => 2,
            'department_id' => $department->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 30,
            'purpose' => 'Visitor registration details pending.',
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->patch(route('admin.appointments.status', $appointment), ['status' => 'completed'])
            ->assertRedirect(route('admin.appointments.index'));

        $this->assertDatabaseHas('visitor_appointments', ['id' => $appointment->id, 'status' => 'completed']);
    }

    public function test_email_link_starts_registration_and_uses_visitor_submitted_booking_details(): void
    {
        $department = Department::create(['name' => 'Customer Service']);
        $person = DepartmentPerson::create([
            'department_id' => $department->id,
            'name' => 'Ms. Jane Perera',
        ]);
        $token = 'visitor-registration-test-token';
        $appointment = VisitorAppointment::create([
            'reference' => 'APT-260813-ABCDE',
            'visitor_name' => 'Nimal Perera',
            'email' => 'nimal@example.com',
            'phone' => '0771234567',
            'visitor_count' => 3,
            'department_id' => $department->id,
            'department_person_id' => $person->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 30,
            'purpose' => 'Account review meeting',
            'registration_token' => hash('sha256', $token),
        ]);

        $this->get(route('visitor.appointments.start', [$appointment, $token]))
            ->assertRedirect(route('visitor.upload_document'))
            ->assertSessionHas('appointment_registration_id', $appointment->id);

        $verification = [
            'session_id' => '99999999-2222-4333-8444-555555555555',
            'document_type' => 'nic',
            'full_name' => 'Nimal Perera',
            'document_number' => '199012345678',
            'face_verification_status' => 'verified',
        ];

        $this->withSession([
            'verification' => $verification,
            'visitor_category' => ['name' => 'Adult'],
            'appointment_registration_id' => $appointment->id,
        ])->post(route('visitor.confirm'), [
            'mobile_number' => '771234567',
            'same_as_mobile' => '1',
            'department' => 'Tampered department',
            'person_to_meet' => 'Tampered person',
            'visitor_count' => 2,
            'purpose' => 'Visitor submitted purpose',
        ])->assertOk()
            ->assertSee('Visitor submitted purpose')
            ->assertSee('Customer Service');

        $this->assertDatabaseHas('verified_visitors', [
            'visitor_appointment_id' => $appointment->id,
            'department' => 'Customer Service',
            'person_to_meet' => 'Ms. Jane Perera',
            'visitor_count' => 2,
            'purpose' => 'Visitor submitted purpose',
        ]);
        $this->assertNotNull($appointment->fresh()->registration_completed_at);
    }
}
