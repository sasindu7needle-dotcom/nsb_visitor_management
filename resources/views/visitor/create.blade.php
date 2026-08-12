<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details — NSB Visitor Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body.visit-request-page{min-height:100vh;background:linear-gradient(145deg,#eef2f7,#f8fafc 55%,#eef6c9);font-family:Inter,sans-serif}
        .visit-request-shell{display:grid;min-height:100vh;place-items:center;padding:28px 16px}
        .visit-request-card{width:min(100%,430px);overflow:hidden;background:#fff;border:1px solid #dbe3ea;border-radius:28px;box-shadow:0 22px 70px rgba(27,39,62,.16)}
        .visit-request-top{padding:25px 25px 18px;background:linear-gradient(135deg,#17233f,#233766);color:#fff}
        .visit-request-top>span{font-size:10px;font-weight:800;letter-spacing:.12em;color:#2563EB}
        .visit-request-top h1{margin:7px 0 3px;font-size:24px}.visit-request-top p{margin:0;color:#c9d3e4;font-size:12px}
        .verified-person{display:grid;grid-template-columns:78px 1fr;gap:15px;align-items:center;padding:20px 24px;border-bottom:1px solid #edf0f3}
        .verified-person img{width:78px;height:88px;object-fit:cover;border:3px solid #fff;border-radius:13px;box-shadow:0 5px 17px rgba(25,38,59,.2)}
        .verified-person span{display:block;margin-bottom:4px;color:#7b8797;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
        .verified-person h2{margin:0 0 7px;color:#172033;font-size:18px}.verified-person strong{color:#4d5a6b;font-size:12px}
        .verified-person small{display:block;margin-top:5px;color:#7b8797;font-size:10px;line-height:1.4}
        .verified-tick{display:inline-flex!important;width:max-content;align-items:center;margin-top:8px!important;padding:4px 8px;color:#426300!important;background:#edf7c6;border-radius:99px;font-size:9px!important}
        .visit-request-form{display:grid;gap:15px;padding:21px 24px 25px}
        .visit-field{display:grid;gap:7px}.visit-field>span{color:#273246;font-size:11px;font-weight:800}
        .visit-field select,.visit-field input,.visit-field textarea{width:100%;height:46px;padding:0 13px;color:#172033;background:#fff;border:1px solid #ced7e1;border-radius:10px;font:600 13px Inter,sans-serif;outline:none;box-sizing:border-box}
        .visit-field textarea{height:92px;padding-top:12px;resize:vertical}.visit-field select:focus,.visit-field input:focus,.visit-field textarea:focus{border-color:#7ea7ff;box-shadow:0 0 0 3px rgba(51,111,238,.12)}
        .phone-entry{display:grid;grid-template-columns:58px 1fr}.phone-entry b{display:grid;height:46px;place-items:center;color:#4e5b6b;background:#f1f4f7;border:1px solid #ced7e1;border-right:0;border-radius:10px 0 0 10px;font-size:12px}.phone-entry input{border-radius:0 10px 10px 0}
        .visitor-counter{display:grid;grid-template-columns:46px 1fr 46px;gap:8px}.visitor-counter button{height:46px;color:#214fb2;background:#eef4ff;border:1px solid #cbd9f4;border-radius:10px;font-size:23px;cursor:pointer}.visitor-counter input{text-align:center;font-size:16px;font-weight:800}
        .visit-submit{height:50px;margin-top:3px;color:#fff;background:#1769ed;border:0;border-radius:11px;box-shadow:0 9px 20px rgba(23,105,237,.27);font:800 14px Inter,sans-serif;cursor:pointer}
        .visit-submit:hover{background:#0e5cd8}.form-error-msg{color:#c63e47;font-size:10px}
        @media(max-width:460px){.visit-request-shell{padding:0}.visit-request-card{min-height:100vh;border-radius:0}.visit-request-top,.visit-request-form{padding-left:20px;padding-right:20px}}
    </style>
</head>
<body class="visit-request-page">
<main class="visit-request-shell">
    <section class="visit-request-card" aria-labelledby="visit-title">
        <header class="visit-request-top">
            <span>{{ $appointment ? 'SCHEDULED APPOINTMENT' : 'PROFILE PHOTO CAPTURED' }}</span>
            <h1 id="visit-title">Who are you visiting?</h1>
            <p>{{ $appointment ? 'Your booking details have been reserved by NSB.' : 'Complete the visit details for security approval.' }}</p>
        </header>

        @if($appointment)
            <div style="margin:18px 24px 0;padding:13px 14px;background:#eef4ff;border:1px solid #cbd9f4;border-radius:10px;color:#1d4ed8;font-size:11px;line-height:1.55">
                <strong>{{ $appointment->reference }}</strong><br>
                Arrival: {{ $appointment->scheduled_at->format('D, d M Y, h:i A') }}
            </div>
        @endif

        <div class="verified-person">
            <img src="{{ route('visitor.session_photo', ['type' => 'selfie']) }}" alt="Captured profile photo" onerror="this.onerror=null;this.src='{{ route('visitor.session_photo', ['type' => 'photo']) }}'">
            <div>
                <span>Verified visitor</span>
                <h2>{{ data_get($verification, 'full_name', 'Visitor') }}</h2>
                <strong>{{ strtoupper(str_replace('_', ' ', $type)) }}: {{ data_get($verification, 'document_number', 'Not detected') }}</strong>
                <small>{{ data_get($verification, 'address', 'Address not detected') }}</small>
                @if(data_get($category, 'entrance_fee') !== null)<small>Entrance fee: LKR {{ number_format((float) data_get($category, 'entrance_fee'), 2) }}</small>@endif
                <span class="verified-tick">✓ Identity recorded and profile photo saved</span>
            </div>
        </div>

        @error('verification')
            <p class="form-error-msg" style="margin:16px 24px 0">{{ $message }}</p>
        @enderror

        @php
            $recordedName = data_get($verification, 'full_name') ?: data_get($verification, 'full_name_latin');
            $nameLetterCount = strlen((string) preg_replace('/[^A-Za-z]/', '', (string) $recordedName));
            $hasReliableRecordedName = count(preg_split('/\s+/', trim((string) $recordedName), -1, PREG_SPLIT_NO_EMPTY)) >= 2
                && $nameLetterCount >= 6
                && preg_match('/[A-Za-z]{3}/', (string) $recordedName);
            $appointmentPhone = preg_replace('/\D+/', '', (string) ($appointment?->phone ?? ''));
            $appointmentPhone = str_starts_with($appointmentPhone, '94') ? substr($appointmentPhone, 2) : $appointmentPhone;
            $appointmentPhone = str_starts_with($appointmentPhone, '0') ? substr($appointmentPhone, 1) : $appointmentPhone;
            $appointmentPhone = substr($appointmentPhone, -9);
        @endphp
        <form method="POST" action="{{ route('visitor.confirm') }}" class="visit-request-form">
            @csrf
            <input type="hidden" name="same_as_mobile" value="1">

            <label class="visit-field">
                <span>Full name *</span>
                <input name="full_name" value="{{ old('full_name', $recordedName) }}" maxlength="180" required @readonly($hasReliableRecordedName)>
                <small style="color:#7b8797;font-size:9px">{{ $hasReliableRecordedName ? 'Recorded from the verified identity document.' : 'Please correct the name shown on the verified identity document.' }}</small>
                @error('full_name')<small class="form-error-msg">{{ $message }}</small>@enderror
            </label>

            <label class="visit-field">
                <span>NIC / ID number *</span>
                <input name="document_number" value="{{ old('document_number', data_get($verification, 'document_number')) }}" maxlength="30" style="text-transform:uppercase" required @readonly(filled(data_get($verification, 'document_number')))>
                <small style="color:#7b8797;font-size:9px">{{ filled(data_get($verification, 'document_number')) ? 'Recorded from the verified identity document.' : 'Enter the NIC or identity number shown on the document.' }}</small>
                @error('document_number')<small class="form-error-msg">{{ $message }}</small>@enderror
            </label>

            @if($appointment)
                <label class="visit-field"><span>Department</span><input value="{{ $appointment->department?->name }}" readonly><small style="color:#7b8797;font-size:9px">Reserved by NSB for this appointment.</small></label>
                <label class="visit-field"><span>Person to meet</span><input value="{{ $appointment->personToMeet?->name ?? 'No specific person' }}" readonly><small style="color:#7b8797;font-size:9px">Reserved by NSB for this appointment.</small></label>
            @else
                <label class="visit-field">
                    <span>Department *</span>
                    <select id="departmentSelect" name="department" required>
                        <option value="" disabled @selected(!old('department'))>Select department</option>
                        @foreach($departments as $department)<option value="{{ $department->name }}" @selected(old('department') === $department->name)>{{ $department->name }}</option>@endforeach
                    </select>
                    @error('department')<small class="form-error-msg">{{ $message }}</small>@enderror
                </label>
                <label class="visit-field">
                    <span>Person to meet *</span>
                    <select id="personSelect" name="person_to_meet" required disabled><option value="" selected>Select department first</option></select>
                    @error('person_to_meet')<small class="form-error-msg">{{ $message }}</small>@enderror
                </label>
            @endif

            <label class="visit-field">
                <span>Mobile number *</span>
                <span class="phone-entry"><b>+94</b><input id="mobileNumber" name="mobile_number" type="tel" inputmode="numeric" maxlength="9" value="{{ old('mobile_number', $appointmentPhone) }}" placeholder="77 123 4567" required autocomplete="tel"></span>
                <small style="color:#7b8797;font-size:9px">WhatsApp contact: Same as Mobile</small>
                @error('mobile_number')<small class="form-error-msg">{{ $message }}</small>@enderror
            </label>

            <label class="visit-field">
                <span>Purpose of visit *</span>
                <textarea name="purpose" maxlength="1000" required placeholder="Briefly describe the meeting or service needed.">{{ old('purpose') }}</textarea>
                @error('purpose')<small class="form-error-msg">{{ $message }}</small>@enderror
            </label>

            <label class="visit-field">
                <span>Number of visitors with you *</span>
                <span class="visitor-counter">
                    <button type="button" id="visitorMinus" aria-label="Decrease visitor count">−</button>
                    <input id="visitorCount" name="visitor_count" type="number" min="1" max="20" value="{{ old('visitor_count', 1) }}" required readonly>
                    <button type="button" id="visitorPlus" aria-label="Increase visitor count">+</button>
                </span>
                @error('visitor_count')<small class="form-error-msg">{{ $message }}</small>@enderror
            </label>

            <button type="submit" class="visit-submit">Submit visit request · Next</button>
        </form>
    </section>
</main>
<script>
    const departmentSelect = document.getElementById('departmentSelect');
    const personSelect = document.getElementById('personSelect');
    const peopleByDepartment = @json($departments->mapWithKeys(fn ($department) => [$department->name => $department->people->pluck('name')->values()])->all());
    const selectedPerson = @json(old('person_to_meet'));

    const populatePeople = () => {
        const people = peopleByDepartment[departmentSelect.value] || [];
        personSelect.replaceChildren();
        const prompt = new Option(people.length ? 'Select person' : 'No people available for this department', '');
        prompt.disabled = true;
        prompt.selected = true;
        personSelect.add(prompt);

        people.forEach(name => {
            const option = new Option(name, name);
            option.selected = name === selectedPerson;
            personSelect.add(option);
        });

        personSelect.disabled = people.length === 0;
    };

    if (departmentSelect) {
        departmentSelect.addEventListener('change', populatePeople);
        populatePeople();
    }

    const visitorCount = document.getElementById('visitorCount');
    document.getElementById('visitorMinus').addEventListener('click', () => visitorCount.value = Math.max(1, Number(visitorCount.value) - 1));
    document.getElementById('visitorPlus').addEventListener('click', () => visitorCount.value = Math.min(20, Number(visitorCount.value) + 1));
    document.getElementById('mobileNumber').addEventListener('input', event => event.target.value = event.target.value.replace(/\D/g, '').slice(0, 9));
</script>
</body>
</html>
