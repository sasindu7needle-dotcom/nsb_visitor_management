@extends('layouts.admin')

@section('title', 'Appointment Visitor Review')

@section('header')
    <div>
        <span class="tagline no-margin">REGISTERED APPOINTMENT</span>
        <h1>Visitor review<span>.</span></h1>
        <p>{{ $appointment->scheduled_at->format('l, d F Y \\a\\t h:i A') }} &middot; {{ $appointment->reference }}</p>
    </div>
@endsection

@section('content')
    <style>
        .appointment-review{max-width:760px}.appointment-review-back{display:inline-flex;margin:0 0 16px;color:#2563eb;font-size:12px;font-weight:800;text-decoration:none}.appointment-review-message{margin:0 0 16px;padding:11px 14px;border:1px solid #93c5fd;border-radius:9px;background:#eff6ff;color:#1e3a8a;font-size:12px;font-weight:700}.appointment-review-message.error{border-color:#efb7bc;background:#fff0f1;color:#94232d}.appointment-review .security-alert-card{border-color:#bfdbfe}.appointment-review .security-actions{border-top:1px solid #e5e7eb}.appointment-review-complete{padding:20px 22px;color:#475569;font-size:13px;font-weight:700;text-align:center}
    </style>

    <div class="appointment-review">
        <a class="appointment-review-back" href="{{ route('admin.appointments.index', ['tab' => 'upcoming', 'date' => $appointment->scheduled_at->toDateString()]) }}">&larr; Back to upcoming visits</a>
        @if(session('status'))<div class="appointment-review-message" role="status">{{ session('status') }}</div>@endif
        @error('visitor_request')<div class="appointment-review-message error" role="alert">{{ $message }}</div>@enderror

        <article class="security-alert-card">
            <div class="security-card-heading">
                <div>
                    <span>NEW VISITOR</span>
                    <strong>{{ $visitor->full_name ?: $appointment->visitor_name }}</strong>
                </div>
                <span class="security-status">{{ $visitor->approval_status === 'pending' ? 'Pending' : ucfirst($visitor->approval_status) }}</span>
            </div>
            <div class="security-visitor">
                @if($visitor->selfie_path)
                    <img src="{{ route('admin.visitors.selfie', ['visitor' => $visitor, 'v' => $visitor->updated_at?->format('Uu')]) }}" alt="Photo of {{ $visitor->full_name }}">
                @else
                    <div class="security-avatar">{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</div>
                @endif
                <dl class="security-details">
                    <dt>NIC / ID</dt><dd>{{ $visitor->document_number ?: 'Not provided' }}</dd>
                    <dt>Department</dt><dd>{{ $visitor->department ?: $appointment->department?->name ?: 'Not specified' }}</dd>
                    <dt>Person to meet</dt><dd>{{ $visitor->person_to_meet ?: $appointment->personToMeet?->name ?: 'Not specified' }}</dd>
                    <dt>Visitors</dt><dd>{{ $visitor->visitor_count ?: $appointment->visitor_count }}</dd>
                    <dt>Phone</dt><dd>{{ $visitor->mobile_number ?: $appointment->phone }}</dd>
                    <dt>Gate</dt><dd>{{ $visitor->expected_gate ?: 'Main Gate' }}</dd>
                </dl>
            </div>
            @if($visitor->approval_status === 'pending')
                <div class="security-actions">
                    <form method="POST" action="{{ route('admin.dashboard.visitor_requests.decide', $visitor) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="decision" value="reject">
                        <input type="hidden" name="return_to" value="appointment">
                        <button class="security-reject" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"></path></svg>Reject</button>
                    </form>
                    <form class="security-allow-form" method="POST" action="{{ route('admin.dashboard.visitor_requests.decide', $visitor) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="decision" value="allow">
                        <input type="hidden" name="return_to" value="appointment">
                        <label class="security-pass-number">Visitor pass ID<input name="visitor_pass_number" maxlength="50" placeholder="e.g. VP-014"></label>
                        <label class="security-pass-check"><input type="checkbox" name="pass_issued" value="1" checked> <span>Pass handed to visitor</span></label>
                        <button class="security-allow" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>Allow entry</button>
                    </form>
                </div>
            @else
                <div class="appointment-review-complete">This appointment visitor has already been {{ $visitor->approval_status }}.</div>
            @endif
        </article>
    </div>
@endsection
