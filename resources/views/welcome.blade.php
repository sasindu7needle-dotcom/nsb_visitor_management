<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management — NSB Visitor Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page welcome-page">
    <main class="hero">
        <div class="hero-content">
            <div class="tagline">Secure. Manage. Govern.</div>
            <h1 class="headline">Full cycle Visitor Management<span class="dot">.</span></h1>
            <p class="description">
                NSB Visitor Management provides a secure, streamlined way to register, verify and manage every visitor entering National Savings Bank.
            </p>
            <div class="cta-buttons">
                <a href="{{ route('visitor.start') }}" class="btn btn-primary btn-large">Register</a>
                <a href="{{ route('visitor.returning') }}" class="btn btn-secondary btn-large">Returning visitor</a>
            </div>
        </div>

        <div class="hero-visual" aria-hidden="true">
            @include('visitor.partials.checkin-illustration')
        </div>
    </main>
</body>
</html>
