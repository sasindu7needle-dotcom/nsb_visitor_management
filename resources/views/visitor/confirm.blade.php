<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Details — NSB Visitor Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page visitor-confirmation-page">
    <main class="registration-shell confirmation-shell">
        <div class="registration-background" aria-hidden="true">
            <div class="registration-background-glow"></div>
            <div class="registration-background-art">@include('visitor.partials.checkin-illustration')</div>
            <span class="registration-accent registration-accent-lime"></span>
            <span class="registration-accent registration-accent-coral"></span>
        </div>

        <section class="registration-card confirmation-card" aria-labelledby="confirmation-title">
            <div class="registration-heading confirmation-heading">
                <span class="tagline no-margin">FINAL REVIEW</span>
                <h1 id="confirmation-title" class="headline">Confirm your details<span class="dot">.</span></h1>
                <p>Review your visitor information and wait for security approval.</p>
            </div>

            <div class="confirmation-profile">
                <div class="visitor-photo-frame">
                    @if(data_get($details, 'selfie_path'))
                        <img src="{{ route('visitor.session_photo', ['type' => 'selfie']) }}" alt="Live camera visitor photo" onerror="this.onerror=null; this.src='{{ route('visitor.session_photo', ['type' => 'photo']) }}';">
                    @elseif(data_get($details, 'photo_url'))
                        <img src="{{ $details['photo_url'] }}" alt="Visitor profile photo" onerror="this.onerror=null; this.src='{{ route('visitor.session_photo', ['type' => 'selfie']) }}';">
                    @elseif(data_get($details, 'photo_path'))
                        <img src="{{ route('visitor.session_photo', ['type' => 'selfie']) }}" alt="Visitor profile photo">
                    @else
                        <div class="visitor-photo-placeholder" aria-label="Visitor photo unavailable">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
                        </div>
                    @endif
                    <span class="verified-photo-badge">VISITOR PHOTO</span>
                </div>

                <div class="confirmation-details-grid">
                    @foreach([
                        'full_name' => 'Full Name',
                        'mobile_number' => 'Mobile Number',
                        'department' => 'Department',
                        'person_to_meet' => 'Person to Meet',
                        'visitor_count' => 'Number of Visitors',
                        'purpose' => 'Purpose of Visit',
                        'expected_gate' => 'Expected Gate'
                    ] as $key => $label)
                        <div class="confirmation-detail">
                            <span>{{ $label }}</span>
                            <strong>
                                @if($key === 'mobile_number')
                                    +94 {{ $details[$key] }}
                                @else
                                    {{ filled($details[$key] ?? null) ? $details[$key] : '—' }}
                                @endif
                            </strong>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="margin:0 0 18px;padding:13px 15px;color:#4e5c19;background:#f3f8dc;border:1px solid #d8e69d;border-radius:10px;font-size:11px;font-weight:700;line-height:1.5">
                Your visit request was sent to the security officer. Gate access remains pending until security selects Allow.
            </div>

            <div class="confirmation-actions">
                <a href="{{ route('visitor.create', ['type' => $details['document_type']]) }}" class="btn-back-link">Back to edit</a>
                <a href="{{ url('/') }}" class="btn btn-primary btn-large confirmation-pay-button" style="text-decoration:none">Finish</a>
            </div>
        </section>
        <footer class="registration-trust">Your visitor information is stored securely and is visible only to authorized staff.</footer>
    </main>
</body>
</html>
