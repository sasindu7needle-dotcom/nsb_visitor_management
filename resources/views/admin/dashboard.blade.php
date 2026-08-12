@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('header')
    <div>
        <span class="tagline no-margin">ADMIN OVERVIEW</span>
        <h1>Visitor Dashboard<span>.</span></h1>
        <p>{{ now()->format('l, F j, Y') }}</p>
    </div>
@endsection

@section('content')
    <style>
        .admin-dashboard-message{margin:0 0 14px;padding:11px 14px;border:1px solid #93C5FD;border-radius:9px;background:#EFF6FF;color:#1e3a8a;font-size:12px;font-weight:700}.admin-dashboard-message.error{border-color:#efb7bc;background:#fff0f1;color:#94232d}
        button.admin-stat-card{width:100%;border:0;text-align:left;font-family:inherit;cursor:pointer}button.admin-stat-card:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(20,34,57,.12)}button.admin-stat-card:focus-visible{outline:3px solid #2563EB;outline-offset:3px}
        .admin-stat-detail{margin:0 0 22px;padding:20px 22px;border:1px solid #dbe3ea;border-radius:13px;background:#fff;box-shadow:0 9px 24px rgba(24,33,46,.06)}.admin-stat-detail[hidden]{display:none}.admin-stat-detail-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:15px}.admin-stat-detail-head span{display:block;color:#2563EB;font-size:9px;font-weight:800;letter-spacing:.09em}.admin-stat-detail-head h2{margin:4px 0 0;color:#17233f;font-size:18px}.admin-stat-detail-close{padding:7px 11px;border:1px solid #cad5e2;border-radius:8px;color:#334155;background:#fff;font:700 11px Inter,sans-serif;cursor:pointer}.admin-stat-detail-close:hover{background:#f1f5f9}.admin-stat-detail-empty{padding:22px;text-align:center;color:#75808d;font-size:12px}
        .admin-stat-checkout{padding:7px 11px;border:0;border-radius:7px;color:#fff;background:#2563EB;font:700 11px Inter,sans-serif;cursor:pointer}.admin-stat-checkout:hover{background:#1d4ed8}
        .admin-stat-visitor-link{padding:0;border:0;color:#1d4ed8;background:transparent;font:700 12px Inter,sans-serif;text-align:left;cursor:pointer}.admin-stat-visitor-link:hover{text-decoration:underline}.dashboard-profile-dialog .admin-dialog-grid{margin-top:18px}.dashboard-profile-visits{margin-top:18px}.dashboard-profile-visits>span{display:block;margin-bottom:8px;color:#2563EB;font-size:9px;font-weight:800;letter-spacing:.08em}
    </style>
            <section class="admin-stat-grid" aria-label="Visitor statistics">
                @foreach([
                    ['id' => 'total', 'label' => 'Total Visitors', 'value' => $stats['total'], 'tone' => 'lime'],
                    ['id' => 'today', 'label' => 'Arrivals Today', 'value' => $stats['today'], 'tone' => 'coral'],
                    ['id' => 'inside', 'label' => 'Currently Inside', 'value' => $stats['checked_in'], 'tone' => 'black', 'key' => 'inside'],
                    ['id' => 'checked_out', 'label' => 'Checked Out', 'value' => $stats['checked_out'], 'tone' => 'slate']
                ] as $stat)
                    <button type="button" class="admin-stat-card admin-stat-{{ $stat['tone'] }}" data-stat-details="{{ $stat['id'] }}" aria-controls="stat-detail-{{ $stat['id'] }}" aria-expanded="false"><div><span>{{ $stat['label'] }}</span><strong @if(isset($stat['key'])) data-live-count="{{ $stat['key'] }}" @endif>{{ number_format($stat['value']) }}</strong></div><i></i></button>
                @endforeach
            </section>
            @foreach($statDetails as $key => $detail)
                <section id="stat-detail-{{ $key }}" class="admin-stat-detail" aria-labelledby="stat-detail-title-{{ $key }}" hidden>
                    <header class="admin-stat-detail-head"><div><span>VISITOR DETAILS</span><h2 id="stat-detail-title-{{ $key }}">{{ $detail['title'] }}</h2></div><button type="button" class="admin-stat-detail-close" data-close-stat-details>Close</button></header>
                    @if($detail['visitors']->isNotEmpty())
                        <div class="table-responsive"><table class="admin-table"><thead><tr><th>Visitor</th><th>NIC / ID</th><th>Phone</th><th>Status</th>@if($key === 'inside')<th>Visitor pass ID</th>@endif<th>{{ $detail['time_label'] }}</th>@if($key === 'inside')<th>Action</th>@endif</tr></thead><tbody>@foreach($detail['visitors'] as $visitor)<tr><td><button type="button" class="admin-stat-visitor-link" data-dashboard-profile="{{ $visitor->id }}">{{ $visitor->full_name ?: $visitor->full_name_latin ?: 'Unnamed visitor' }}</button></td><td>{{ $visitor->document_number ?: '—' }}</td><td>{{ $visitor->mobile_number ?: '—' }}</td><td><span class="{{ $visitor->checkin_status ? 'badge-pill-checkedin' : 'badge-pill-checkedout' }}">{{ $visitor->checkin_status ? 'Inside' : 'Outside' }}</span></td>@if($key === 'inside')<td>{{ $visitor->visitor_pass_number ?: '—' }}</td>@endif<td>@if($key === 'inside'){{ $visitor->checked_in_at?->format('M j, g:i A') ?: '—' }}@elseif($key === 'checked_out'){{ $visitor->checked_out_at?->format('M j, g:i A') ?: '—' }}@else{{ ($visitor->verified_at ?: $visitor->created_at)?->format('M j, g:i A') ?: '—' }}@endif</td>@if($key === 'inside')<td><form method="POST" action="{{ route('admin.visitors.checkout', $visitor) }}">@csrf @method('PATCH')<button class="admin-stat-checkout" type="submit">Check out</button></form></td>@endif</tr>@endforeach</tbody></table></div>
                    @else
                        <p class="admin-stat-detail-empty">No visitor records match this statistic.</p>
                    @endif
                </section>
            @endforeach
            @foreach($profileVisitors as $visitor)
                <dialog id="dashboard-visitor-profile-{{ $visitor->id }}" class="admin-visitor-dialog dashboard-profile-dialog">
                    <div class="admin-dialog-heading"><div><span>VISITOR PROFILE</span><h2>{{ $visitor->full_name ?: $visitor->full_name_latin ?: 'Visitor details' }}</h2></div><button type="button" data-close-dashboard-profile aria-label="Close">×</button></div>
                    <div class="admin-dialog-grid">
                        @foreach([
                            'NIC / ID' => $visitor->document_number,
                            'Phone' => $visitor->mobile_number,
                            'Department' => $visitor->department,
                            'Person to meet' => $visitor->person_to_meet,
                            'Registered' => ($visitor->verified_at ?: $visitor->created_at)?->format('M j, Y · g:i A'),
                            'Current status' => $visitor->checkin_status ? 'INSIDE' : 'OUTSIDE',
                            'Visitor pass' => $visitor->visitor_pass_number,
                            'Pass status' => ! $visitor->visitor_pass_issued_at ? 'NOT ISSUED' : ($visitor->visitor_pass_returned_at ? 'RETURNED' : 'ISSUED'),
                        ] as $label => $value)<div><span>{{ $label }}</span><strong>{{ filled($value) ? $value : '—' }}</strong></div>@endforeach
                    </div>
                    <section class="dashboard-profile-visits"><span>COMPLETE VISIT HISTORY</span><div class="table-responsive"><table class="admin-table"><thead><tr><th>Visit date</th><th>Check-in</th><th>Check-out</th><th>Gate</th><th>Duration</th></tr></thead><tbody>@forelse($visitor->activity_rows as $activity)<tr><td>{{ $activity['in']->scanned_at->format('M j, Y') }}</td><td>{{ $activity['in']->scanned_at->format('g:i A') }}</td><td>{{ $activity['out']?->scanned_at?->format('g:i A') ?: 'Inside now' }}</td><td>{{ $activity['in']->gate }}{{ $activity['out'] ? ' / '.$activity['out']->gate : '' }}</td><td>{{ $activity['duration_minutes'] !== null ? intdiv($activity['duration_minutes'], 60).'h '.($activity['duration_minutes'] % 60).'m' : '—' }}</td></tr>@empty<tr><td colspan="5" class="admin-stat-detail-empty">No check-in or checkout history recorded.</td></tr>@endforelse</tbody></table></div></section>
                    <div class="admin-dialog-actions"><button type="button" class="admin-modal-close-button" data-close-dashboard-profile>Close</button></div>
                </dialog>
            @endforeach
            @if(session('status'))<div class="admin-dashboard-message" role="status">{{ session('status') }}</div>@endif
            @error('visitor_request')<div class="admin-dashboard-message error" role="alert">{{ $message }}</div>@enderror
            @error('visitor_pass')<div class="admin-dashboard-message error" role="alert">{{ $message }}</div>@enderror
            @error('checkout')<div class="admin-dashboard-message error" role="alert">{{ $message }}</div>@enderror

            <section class="security-alerts" aria-labelledby="security-alert-title">
                <header class="security-alerts-heading">
                    <div class="security-alerts-heading-copy">
                        <span class="security-alerts-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.6 2.9 8.3 7 10 4.1-1.7 7-5.4 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                        </span>
                        <div>
                            <span class="security-eyebrow">VISITOR APPROVAL QUEUE</span>
                            <h2 id="security-alert-title">Security Officer — New Visitor Alert</h2>
                        </div>
                    </div>
                    <div class="security-alert-summary">
                        <span class="security-live-dot" aria-hidden="true"></span>
                        <strong>{{ $pendingVisitors->count() }} {{ Str::plural('request', $pendingVisitors->count()) }}</strong>
                        <small>awaiting review</small>
                    </div>
                </header>
                @if($pendingVisitors->isNotEmpty())
                    <div class="security-alert-grid">
                        @foreach($pendingVisitors as $visitor)
                            <article class="security-alert-card">
                                <div class="security-card-heading">
                                    <div>
                                        <span>NEW VISITOR</span>
                                        <strong>{{ $visitor->full_name ?: 'Unknown visitor' }}</strong>
                                    </div>
                                    <span class="security-status">Pending</span>
                                </div>
                                <div class="security-visitor">
                                    @if($visitor->selfie_path)
                                        <img src="{{ route('admin.visitors.selfie', ['visitor' => $visitor, 'v' => $visitor->updated_at?->format('Uu')]) }}" alt="Photo of {{ $visitor->full_name }}">
                                    @else
                                        <div class="security-avatar">{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</div>
                                    @endif
                                    <dl class="security-details">
                                        <dt>NIC / ID</dt><dd>{{ $visitor->document_number ?: 'Not provided' }}</dd>
                                        <dt>Department</dt><dd>{{ $visitor->department ?: 'Not specified' }}</dd>
                                        <dt>Person to meet</dt><dd>{{ $visitor->person_to_meet ?: 'Not specified' }}</dd>
                                        <dt>Visitors</dt><dd>{{ $visitor->visitor_count }}</dd>
                                        <dt>Phone</dt><dd>{{ $visitor->mobile_number ?: 'Not provided' }}</dd>
                                        <dt>Gate</dt><dd>{{ $visitor->expected_gate ?: 'Main Gate' }}</dd>
                                    </dl>
                                </div>
                                <div class="security-actions">
                                    <form method="POST" action="{{ route('admin.dashboard.visitor_requests.decide', $visitor) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button class="security-reject" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"></path></svg>Reject</button></form>
                                    <form class="security-allow-form" method="POST" action="{{ route('admin.dashboard.visitor_requests.decide', $visitor) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="decision" value="allow">
                                        <label class="security-pass-number">Visitor pass ID<input name="visitor_pass_number" maxlength="50" placeholder="e.g. VP-014"></label>
                                        <label class="security-pass-check"><input type="checkbox" name="pass_issued" value="1" checked> <span>Pass handed to visitor</span></label>
                                        <button class="security-allow" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>Allow entry</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="security-empty">
                        <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.6 2.9 8.3 7 10 4.1-1.7 7-5.4 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg></span>
                        <div><strong>All requests reviewed</strong><small>No visitors are waiting for security approval.</small></div>
                    </div>
                @endif
            </section>

            <section class="security-alerts returning-face-checks" aria-labelledby="returning-face-check-title">
                <header class="security-alerts-heading">
                    <div class="security-alerts-heading-copy">
                        <span class="security-alerts-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path><path d="m16 12 2 2 3-4"></path></svg>
                        </span>
                        <div>
                            <span class="security-eyebrow">RETURNING VISITOR CHECKS</span>
                            <h2 id="returning-face-check-title">Face Comparison Results</h2>
                        </div>
                    </div>
                    <div class="security-alert-summary">
                        <span class="security-live-dot" aria-hidden="true"></span>
                        <strong>{{ $returningFaceCheckCount }} {{ Str::plural('check', $returningFaceCheckCount) }}</strong>
                        <small>captured</small>
                    </div>
                </header>
                @if($returningFaceChecks->isNotEmpty())
                    <div class="security-alert-grid">
                        @foreach($returningFaceChecks as $faceCheck)
                            @php $visitor = $faceCheck->visitor; @endphp
                            <article class="security-alert-card">
                                <div class="security-card-heading">
                                    <div>
                                        <span>RETURNING VISITOR</span>
                                        <strong>{{ $visitor?->full_name ?: $visitor?->full_name_latin ?: 'Visitor record unavailable' }}</strong>
                                    </div>
                                    <span class="security-status return-face-status-{{ $faceCheck->status }}">{{ strtoupper(str_replace('_', ' ', $faceCheck->status)) }}</span>
                                </div>
                                <div class="security-visitor">
                                    <img src="{{ route('admin.visitors.return_face_photo', ['visitor' => $visitor, 'faceCheck' => $faceCheck, 'v' => $faceCheck->updated_at?->format('Uu')]) }}" alt="Return face photo of {{ $visitor?->full_name }}">
                                    <dl class="security-details">
                                        <dt>NIC / ID</dt><dd>{{ $faceCheck->nic_number }}</dd>
                                        <dt>Similarity</dt><dd>{{ $faceCheck->match_score !== null ? number_format((float) $faceCheck->match_score, 2).'%' : 'Security review required' }}</dd>
                                        <dt>Checked at</dt><dd>{{ $faceCheck->checked_at?->format('M j, Y · g:i A') }}</dd>
                                        <dt>Result</dt><dd>{{ $faceCheck->status === 'same' ? 'Same face' : ($faceCheck->status === 'different' ? 'Different face' : 'Review required') }}</dd>
                                    </dl>
                                </div>
                                <div class="security-return-actions">
                                    <a href="{{ route('admin.visitors.index', ['search' => $faceCheck->nic_number]) }}">Open visitor record</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="security-empty">
                        <span aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg></span>
                        <div><strong>No returning visitor checks yet</strong><small>NIC and face comparisons will appear here after a returning visitor completes the camera check.</small></div>
                    </div>
                @endif
            </section>

            <section class="admin-panel">
                <div class="admin-panel-heading"><div><span>LIVE RECORDS</span><h2>Recent visitors</h2></div><a href="{{ route('admin.visitors.index') }}">View all <span>→</span></a></div>
                <div class="table-responsive">
                    <table class="admin-table admin-recent-visitors-table">
                        <thead><tr><th>Visitor</th><th>Phone</th><th>Purpose</th><th>Arrival</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($recentVisitors as $visitor)
                                @php($mediaVersion = $visitor->updated_at?->format('Uu') ?: $visitor->id)
                                <tr><td><div class="admin-visitor-cell">@if($visitor->selfie_path)<img src="{{ route('admin.visitors.selfie', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="Photo of {{ $visitor->full_name }}">@elseif($visitor->photo_path)<img src="{{ route('admin.visitors.photo', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="Photo of {{ $visitor->full_name }}">@elseif($visitor->photo_url)<img src="{{ $visitor->photo_url }}" alt="Photo of {{ $visitor->full_name }}">@else<span>{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</span>@endif<div><strong>{{ $visitor->full_name ?: 'Unnamed visitor' }}</strong><small>{{ $visitor->document_number ?: 'No document number' }}</small></div></div></td><td>{{ $visitor->mobile_number ?: '—' }}</td><td class="admin-purpose-cell">{{ $visitor->occupation ?: '—' }}{{ $visitor->company ? ' · '.$visitor->company : '' }}</td><td>{{ ($visitor->verified_at ?: $visitor->created_at)?->format('M j, g:i A') }}</td><td><span class="{{ $visitor->checkin_status ? 'badge-pill-checkedin' : 'badge-pill-checkedout' }}">{{ $visitor->checkin_status ? 'Inside' : 'Not inside' }}</span></td></tr>
                            @empty
                                <tr><td colspan="5" class="admin-empty-state">No visitor records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
@endsection

@push('scripts')
<script>
        const dashboardProfileDialogs = [...document.querySelectorAll('.dashboard-profile-dialog')];
        document.querySelectorAll('[data-dashboard-profile]').forEach(button => button.addEventListener('click', () => {
            const profile = document.getElementById(`dashboard-visitor-profile-${button.dataset.dashboardProfile}`);
            if (profile && !profile.open) profile.showModal();
        }));
        document.querySelectorAll('[data-close-dashboard-profile]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
        dashboardProfileDialogs.forEach(dialog => dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); }));
        const statButtons = [...document.querySelectorAll('[data-stat-details]')];
        const statDetailSections = [...document.querySelectorAll('.admin-stat-detail')];
        const closeStatDetails = () => {
            statDetailSections.forEach(section => section.hidden = true);
            statButtons.forEach(button => button.setAttribute('aria-expanded', 'false'));
        };
        statButtons.forEach(button => button.addEventListener('click', () => {
            const detail = document.getElementById(`stat-detail-${button.dataset.statDetails}`);
            const isOpen = detail && !detail.hidden;
            closeStatDetails();
            if (!detail || isOpen) return;
            detail.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            detail.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }));
        document.querySelectorAll('[data-close-stat-details]').forEach(button => button.addEventListener('click', closeStatDetails));
        const initialPendingApprovals = {{ $pendingVisitors->count() }};
        const initialReturningFaceChecks = {{ $returningFaceCheckCount }};
        setInterval(async () => {
            try {
                const response = await fetch(@json(route('admin.dashboard.counts')), {headers:{Accept:'application/json'}});
                if (!response.ok) return;
                const counts = await response.json();
                if (Number(counts.pending_approvals || 0) !== initialPendingApprovals
                    || Number(counts.returning_face_checks || 0) !== initialReturningFaceChecks) {
                    window.location.reload();
                    return;
                }
                document.querySelectorAll('[data-live-count]').forEach(element => element.textContent = Number(counts[element.dataset.liveCount] || 0).toLocaleString());
            } catch (_) {}
        }, 12000);
</script>
@endpush
