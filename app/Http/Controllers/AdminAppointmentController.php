<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentPerson;
use App\Models\VisitorAppointment;
use App\Mail\AppointmentRegistrationMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:schedule,upcoming'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $activeTab = $filters['tab'] ?? 'schedule';
        $selectedDate = $filters['date'] ?? null;

        $departments = Department::query()
            ->where('is_active', true)
            ->with(['people' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        $upcomingVisits = VisitorAppointment::query()
            ->with(['department', 'personToMeet', 'registeredVisitor'])
            ->where('status', 'scheduled')
            ->where(function ($query) {
                // The visitor record is the source of truth for new
                // registrations; retain the timestamp check for any records
                // completed before the visitor-appointment link existed.
                $query->whereHas('registeredVisitor')
                    ->orWhereNotNull('registration_completed_at');
            })
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->when($selectedDate, fn ($query, $date) => $query->whereDate('scheduled_at', $date))
            ->orderBy('scheduled_at')
            ->paginate(12)
            ->withQueryString();

        $upcomingVisitCount = VisitorAppointment::query()
            ->where('status', 'scheduled')
            ->where(function ($query) {
                $query->whereHas('registeredVisitor')
                    ->orWhereNotNull('registration_completed_at');
            })
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->count();

        return view('admin.appointments.index', compact(
            'departments',
            'upcomingVisits',
            'upcomingVisitCount',
            'activeTab',
            'selectedDate',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visitor_name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'company' => ['nullable', 'string', 'max:150'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'department_person_id' => ['nullable', 'integer', 'exists:department_people,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $department = Department::whereKey($validated['department_id'])->where('is_active', true)->first();
        if (! $department) {
            return back()->withInput()->withErrors(['department_id' => 'Please choose an active department.']);
        }

        if (filled($validated['department_person_id'] ?? null)) {
            $personIsAvailable = DepartmentPerson::query()
                ->whereKey($validated['department_person_id'])
                ->where('department_id', $department->id)
                ->where('is_active', true)
                ->exists();

            if (! $personIsAvailable) {
                return back()->withInput()->withErrors(['department_person_id' => 'Please choose an available person from the selected department.']);
            }
        }

        $registrationToken = Str::random(64);

        $appointment = VisitorAppointment::create([
            ...$validated,
            'reference' => $this->newReference(),
            'visitor_name' => trim($validated['visitor_name']),
            'phone' => trim($validated['phone']),
            'company' => filled($validated['company'] ?? null) ? trim($validated['company']) : null,
            'visitor_count' => 1,
            'purpose' => 'Awaiting visitor registration.',
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'created_by' => (string) session('admin_username'),
            'registration_token' => hash('sha256', $registrationToken),
        ]);

        $appointment->load(['department', 'personToMeet']);

        try {
            Mail::to($appointment->email)->send(new AppointmentRegistrationMail($appointment, $registrationToken));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.appointments.index')
                ->with('error', "Appointment {$appointment->reference} was scheduled, but its registration email could not be sent. Check the mail settings and try again.");
        }

        return redirect()->route('admin.appointments.index')
            ->with('status', "Appointment {$appointment->reference} has been scheduled and a registration email was sent to {$appointment->email}.");
    }

    public function updateStatus(Request $request, VisitorAppointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:scheduled,completed,cancelled'],
            'return_tab' => ['nullable', 'in:schedule,upcoming'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $appointment->update(['status' => $validated['status']]);

        return redirect()->route('admin.appointments.index', array_filter([
            'tab' => $validated['return_tab'] ?? null,
            'date' => $validated['return_date'] ?? null,
        ]))
            ->with('status', "Appointment {$appointment->reference} marked as {$validated['status']}.");
    }

    private function newReference(): string
    {
        do {
            $reference = 'APT-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (VisitorAppointment::where('reference', $reference)->exists());

        return $reference;
    }
}
