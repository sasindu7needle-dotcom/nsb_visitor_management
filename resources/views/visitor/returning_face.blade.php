<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Returning Visitor Face Check — NSB Visitor Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page live-face-page">
    <section class="hero">
        <div class="hero-content">
            <a class="face-back" href="{{ url('/') }}">← Back to home</a>
            <div class="tagline">Returning visitor</div>
            <h1 class="headline">Verify your return<span class="dot">.</span></h1>
            <p class="face-intro">Enter your NIC, then take a current photo. We compare it with the photo captured when you registered.</p>

            <div class="face-card">
                <div id="nicStep">
                    <label class="returning-label" for="nicNumber">NIC number</label>
                    <input id="nicNumber" class="returning-input" autocomplete="off" maxlength="20" placeholder="e.g. 199012345678 or 901234567V">
                    <p class="face-error" id="nicError" role="alert"></p>
                    <button type="button" id="findBtn" class="btn btn-primary btn-large form-width-100">Continue</button>
                </div>

                <div id="cameraStep" hidden>
                    <div class="returning-found" id="foundVisitor"></div>
                    <div class="face-status"><span id="statusDot"></span><strong id="statusText">Camera not started</strong></div>
                    <div class="camera-stage">
                        <video id="camera" autoplay muted playsinline></video>
                        <canvas id="captureCanvas" hidden></canvas>
                        <div class="face-guide" id="faceGuide" aria-hidden="true"></div>
                        <div class="camera-placeholder" id="cameraPlaceholder"><strong>Allow camera access to continue</strong><small>Use a clear, well-lit image with only one face.</small></div>
                    </div>
                    <div class="face-tips"><span>Face forward</span><span>Good lighting</span><span>Only one person</span></div>
                    <p class="face-error" id="faceError" role="alert"></p>
                    <button type="button" id="cameraBtn" class="btn btn-secondary btn-large form-width-100">Start camera</button>
                    <button type="button" id="captureBtn" class="btn btn-primary btn-large form-width-100" disabled>Capture and compare</button>
                </div>

                <div id="resultStep" hidden class="returning-result" aria-live="polite"></div>
            </div>
        </div>
        <div class="hero-visual" aria-hidden="true">@include('visitor.partials.checkin-illustration')</div>
    </section>

    <style>
        body.landing-page.live-face-page .face-back { display:inline-flex; margin-bottom:18px; color:#555; font-size:13px; font-weight:600; }
        body.landing-page.live-face-page .face-intro { color:#555; font-size:14px; line-height:1.6; margin:0 0 20px; max-width:520px; }
        body.landing-page.live-face-page .face-card { width:100%; max-width:520px; padding:22px; border:2px solid #e2e8f0; border-radius:14px; background:#fff; box-shadow:0 20px 45px rgba(17,17,17,.08); }
        body.landing-page.live-face-page .returning-label { display:block; margin-bottom:7px; color:#334155; font-size:13px; font-weight:800; }
        body.landing-page.live-face-page .returning-input { width:100%; min-height:50px; padding:12px 14px; border:1px solid #cbd5e1; border-radius:10px; color:#172033; font:600 15px Inter,sans-serif; text-transform:uppercase; }
        body.landing-page.live-face-page .returning-found { margin:0 0 14px; padding:11px 12px; border-radius:9px; color:#1e3a8a; background:#dbeafe; font-size:13px; font-weight:700; }
        body.landing-page.live-face-page .face-status { display:flex; align-items:center; gap:8px; margin-bottom:13px; color:#4b5563; font-size:12px; }
        body.landing-page.live-face-page .face-status span { width:9px; height:9px; border-radius:50%; background:#9ca3af; box-shadow:0 0 0 4px rgba(156,163,175,.14); }
        body.landing-page.live-face-page .face-status.is-live span { background:#2563eb; box-shadow:0 0 0 4px rgba(37,99,235,.22); }
        body.landing-page.live-face-page .camera-stage { position:relative; width:100%; aspect-ratio:4/3; overflow:hidden; border-radius:12px; background:#171717; }
        body.landing-page.live-face-page #camera { display:none; width:100%; height:100%; object-fit:cover; transform:scaleX(-1); }
        body.landing-page.live-face-page .camera-placeholder { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:24px; color:#fff; text-align:center; }
        body.landing-page.live-face-page .camera-placeholder small { color:#b8b8b8; font-size:11px; }
        body.landing-page.live-face-page .face-guide { display:none; position:absolute; z-index:2; left:50%; top:50%; width:43%; height:70%; border:2px solid #2563eb; border-radius:48%; transform:translate(-50%,-50%); box-shadow:0 0 0 999px rgba(0,0,0,.18); pointer-events:none; }
        body.landing-page.live-face-page .face-tips { display:flex; justify-content:center; flex-wrap:wrap; gap:7px; margin:12px 0; }
        body.landing-page.live-face-page .face-tips span { padding:5px 8px; border-radius:99px; background:#eff6ff; color:#1e3a8a; font-size:10px; font-weight:700; }
        body.landing-page.live-face-page .face-error { min-height:18px; margin:8px 0; color:#c43d3d; font-size:12px; line-height:1.4; }
        body.landing-page.live-face-page .face-card .btn { margin-top:9px; }
        body.landing-page.live-face-page .returning-result { padding:20px; border-radius:12px; text-align:center; font-size:14px; font-weight:700; line-height:1.5; }
        body.landing-page.live-face-page .returning-result.same { color:#166534; background:#dcfce7; }
        body.landing-page.live-face-page .returning-result.different, body.landing-page.live-face-page .returning-result.review_required { color:#991b1b; background:#fee2e2; }
        body.landing-page.live-face-page .returning-next { display:flex; align-items:center; justify-content:center; margin-top:16px; color:#fff; text-decoration:none; }
    </style>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const nicStep = document.getElementById('nicStep');
        const cameraStep = document.getElementById('cameraStep');
        const resultStep = document.getElementById('resultStep');
        const nicNumber = document.getElementById('nicNumber');
        const nicError = document.getElementById('nicError');
        const findBtn = document.getElementById('findBtn');
        const camera = document.getElementById('camera');
        const canvas = document.getElementById('captureCanvas');
        const cameraBtn = document.getElementById('cameraBtn');
        const captureBtn = document.getElementById('captureBtn');
        const placeholder = document.getElementById('cameraPlaceholder');
        const faceGuide = document.getElementById('faceGuide');
        const status = document.querySelector('.face-status');
        const statusText = document.getElementById('statusText');
        const faceError = document.getElementById('faceError');
        let stream;

        findBtn.addEventListener('click', async () => {
            const nic = nicNumber.value.trim();
            if (!nic) { nicError.textContent = 'Enter your NIC number.'; return; }
            nicError.textContent = '';
            findBtn.disabled = true;
            findBtn.textContent = 'Finding registration…';
            try {
                const response = await fetch(@json(route('visitor.returning.find')), { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({nic_number:nic}) });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) throw new Error(data.error || 'Unable to find this registration.');
                document.getElementById('foundVisitor').textContent = `Registration found for ${data.visitor.name}. Capture a current face photo.`;
                nicStep.hidden = true;
                cameraStep.hidden = false;
            } catch (error) {
                nicError.textContent = error.message;
            } finally {
                findBtn.disabled = false;
                findBtn.textContent = 'Continue';
            }
        });

        cameraBtn.addEventListener('click', async () => {
            faceError.textContent = '';
            try {
                stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280},height:{ideal:960}},audio:false});
                camera.srcObject = stream; await camera.play();
                camera.style.display = 'block'; placeholder.style.display = 'none'; faceGuide.style.display = 'block';
                status.classList.add('is-live'); statusText.textContent = 'Live camera ready';
                cameraBtn.hidden = true; captureBtn.disabled = false;
            } catch (_) { faceError.textContent = 'Camera access was blocked or unavailable. Allow camera permission and try again.'; }
        });

        captureBtn.addEventListener('click', () => {
            if (!stream || !camera.videoWidth) return;
            captureBtn.disabled = true; captureBtn.textContent = 'Comparing faces…'; faceError.textContent = '';
            canvas.width = camera.videoWidth; canvas.height = camera.videoHeight;
            const context = canvas.getContext('2d'); context.translate(canvas.width, 0); context.scale(-1, 1); context.drawImage(camera, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(async blob => {
                const form = new FormData(); form.append('selfie', blob, 'returning-visitor.jpg');
                try {
                    const response = await fetch(@json(route('visitor.returning.compare')), {method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:form});
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) throw new Error(data.error || 'The face photo could not be compared.');
                    stream.getTracks().forEach(track => track.stop());
                    cameraStep.hidden = true; resultStep.hidden = false; resultStep.className = `returning-result ${data.status}`;
                    const score = data.match_score !== null ? `<br><small>Similarity score: ${Number(data.match_score).toFixed(1)}%</small>` : '';
                    const next = data.redirect_url
                        ? '<a class="btn btn-primary btn-large form-width-100 returning-next" href="' + data.redirect_url + '">Continue to visit details</a>'
                        : '';
                    resultStep.innerHTML = `<strong>${data.status === 'same' ? 'SAME FACE' : data.status === 'different' ? 'DIFFERENT FACE' : 'SECURITY REVIEW REQUIRED'}</strong><br>${data.message}${score}${next}`;
                } catch (error) { faceError.textContent = error.message; captureBtn.disabled = false; captureBtn.textContent = 'Capture and compare'; }
            }, 'image/jpeg', .9);
        });
        window.addEventListener('beforeunload', () => stream?.getTracks().forEach(track => track.stop()));
    </script>
</body>
</html>
