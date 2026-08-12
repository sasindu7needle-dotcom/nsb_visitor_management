@extends('layouts.admin')

@section('title', 'Visitor Appointments')

@section('header')
    <div>
        <span class="tagline no-margin">VISITOR APPOINTMENTS</span>
        <h1>Schedule a visit<span>.</span></h1>
        <p>Create a confirmed visit for a guest and notify the receiving department.</p>
    </div>
@endsection

@section('content')
    <style>
        .appointment-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.85fr);gap:22px;align-items:start}.appointment-form{padding:24px}.appointment-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.appointment-field{display:flex;flex-direction:column;gap:7px}.appointment-field.wide{grid-column:1/-1}.appointment-field span{color:#596579;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.appointment-field b{color:#dc2626}.appointment-field input,.appointment-field select,.appointment-field textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid #d8e0e7;border-radius:9px;background:#fff;color:#172033;font:500 13px Inter,sans-serif;outline:none}.appointment-field textarea{min-height:92px;resize:vertical}.appointment-field input:focus,.appointment-field select:focus,.appointment-field textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.14)}.appointment-helper{margin:5px 0 0;color:#738094;font-size:11px;line-height:1.5}.appointment-actions{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:22px;padding-top:18px;border-top:1px solid #edf0f2}.appointment-actions small{color:#64748b;font-size:11px}.appointment-list{display:grid;gap:12px;padding:18px}.appointment-card{padding:16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.appointment-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.appointment-card h3{margin:0;color:#172033;font-size:14px}.appointment-card p{margin:5px 0 0;color:#64748b;font-size:11px;line-height:1.45}.appointment-time{display:inline-flex;margin-top:13px;padding:7px 9px;border-radius:7px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:800}.appointment-meta{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.appointment-meta span,.appointment-status{padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#526174;font-size:9px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.appointment-status.completed{background:#ecfdf3;color:#15803d}.appointment-status.cancelled{background:#fff1f2;color:#be123c}.appointment-card form{margin-top:13px}.appointment-card button{padding:6px 9px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#475569;font-size:10px;font-weight:800;cursor:pointer}.appointment-card button:hover{border-color:#93c5fd;color:#1d4ed8;background:#eff6ff}.appointment-empty{padding:40px 20px;text-align:center;color:#94a3b8;font-size:12px}.appointment-pagination{padding:0 18px 18px}@media(max-width:980px){.appointment-layout{grid-template-columns:1fr}}@media(max-width:580px){.appointment-form-grid{grid-template-columns:1fr}.appointment-field.wide{grid-column:auto}.appointment-actions{align-items:stretch;flex-direction:column}.appointment-actions .btn{width:100%}}
    </style>

    @if(session('status'))
        <div class="admin-page-alert configuration-success" role="status">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="admin-page-alert admin-alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="admin-page-alert admin-alert-danger" role="alert"><ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="appointment-layout">
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><span>NEW APPOINTMENT</span><h2>Visitor details</h2><p>All fields marked with an asterisk are needed to reserve the time.</p></div></div>
            <form class="appointment-form" method="POST" action="{{ route('admin.appointments.store') }}">
                @csrf
                <div class="appointment-form-grid">
                    <label class="appointment-field"><span>Visitor name <b>*</b></span><input name="visitor_name" value="{{ old('visitor_name') }}" maxlength="180" required autocomplete="name" placeholder="e.g. Nimal Perera"></label>
                    <label class="appointment-field"><span>Phone <b>*</b></span><input name="phone" value="{{ old('phone') }}" maxlength="20" required autocomplete="tel" placeholder="e.g. 077 123 4567"></label>
                    <label class="appointment-field"><span>Email <b>*</b></span><input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" placeholder="visitor@example.com" required><small class="appointment-helper">The visitor receives a secure link to complete registration.</small></label>
                    <label class="appointment-field"><span>Company / organisation</span><input name="company" value="{{ old('company') }}" maxlength="150" placeholder="Optional"></label>
                    <label class="appointment-field"><span>Department <b>*</b></span><select id="appointmentDepartment" name="department_id" required><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></label>
                    <label class="appointment-field"><span>Person to meet</span><select id="appointmentPerson" name="department_person_id" data-selected="{{ old('department_person_id') }}"><option value="">Select a department first</option></select><small class="appointment-helper">Optional, but recommended to help reception direct the visitor.</small></label>
                    <label class="appointment-field"><span>Arrival date &amp; time <b>*</b></span><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" min="{{ now()->addMinute()->format('Y-m-d\\TH:i') }}" required></label>
                    <label class="appointment-field"><span>Expected duration <b>*</b></span><select name="duration_minutes" required>@foreach([15,30,45,60,90,120] as $minutes)<option value="{{ $minutes }}" @selected((int) old('duration_minutes', 30) === $minutes)>{{ $minutes < 60 ? $minutes.' minutes' : ($minutes / 60).' hour'.($minutes > 60 ? 's' : '') }}</option>@endforeach</select></label>
                    <label class="appointment-field wide"><span>Reception notes</span><textarea name="notes" maxlength="1000" placeholder="Accessibility needs, vehicle details, or other useful information.">{{ old('notes') }}</textarea></label>
                </div>
                <div class="appointment-actions"><small>A reference number is created automatically after scheduling.</small><button class="btn btn-primary" type="submit">Schedule appointment <span>→</span></button></div>
            </form>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-heading"><div><span>APPOINTMENT QUEUE</span><h2>Upcoming visits</h2><p>{{ $appointments->total() }} scheduled or completed appointment{{ $appointments->total() === 1 ? '' : 's' }}.</p></div></div>
            <div class="appointment-list">
                @forelse($appointments as $appointment)
                    <article class="appointment-card">
                        <div class="appointment-card-top"><div><h3>{{ $appointment->visitor_name }}</h3><p>{{ $appointment->reference }} · {{ $appointment->phone }}</p></div><span class="appointment-status {{ $appointment->status }}">{{ $appointment->status }}</span></div>
                        <div class="appointment-time">{{ $appointment->scheduled_at->format('D, d M Y · h:i A') }} · {{ $appointment->duration_minutes }} min</div>
                        <p>{{ $appointment->registration_completed_at ? $appointment->purpose : 'Awaiting visitor registration.' }}</p>
                        <div class="appointment-meta"><span>{{ $appointment->department?->name }}</span>@if($appointment->personToMeet)<span>{{ $appointment->personToMeet->name }}</span>@endif<span>{{ $appointment->visitor_count }} visitor{{ $appointment->visitor_count === 1 ? '' : 's' }}</span></div>
                        @if($appointment->status === 'scheduled')<form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">@csrf @method('PATCH')<button name="status" value="completed" type="submit">Mark completed</button><button name="status" value="cancelled" type="submit" style="margin-left:6px;">Cancel appointment</button></form>@endif
                    </article>
                @empty
                    <div class="appointment-empty">No appointments yet. Create the first visitor appointment here.</div>
                @endforelse
            </div>
            @if($appointments->hasPages())<div class="appointment-pagination">{{ $appointments->links() }}</div>@endif
        </section>
    </div>
@endsection

@push('scripts')
<script>
    const appointmentPeople = @json($departments->mapWithKeys(fn ($department) => [$department->id => $department->people->map(fn ($person) => ['id' => $person->id, 'name' => $person->name, 'designation' => $person->designation])->values()]));
    const appointmentDepartment = document.getElementById('appointmentDepartment');
    const appointmentPerson = document.getElementById('appointmentPerson');
    const setAppointmentPeople = () => {
        const selected = appointmentPerson.dataset.selected;
        const people = appointmentPeople[appointmentDepartment.value] || [];
        appointmentPerson.innerHTML = '<option value="">No specific person</option>' + people.map(person => `<option value="${person.id}">${person.name}${person.designation ? ' — ' + person.designation : ''}</option>`).join('');
        appointmentPerson.value = selected;
        appointmentPerson.dataset.selected = '';
    };
    appointmentDepartment.addEventListener('change', setAppointmentPeople);
    setAppointmentPeople();
</script>
@endpush
