<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Visitor;
use App\Models\VerifiedVisitor;
use App\Models\Department;
use App\Models\VisitorAppointment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use F9WebLtd\QrCode\Facades\QrCode;

class VisitorController extends Controller
{
    /**
     * Begin a completely new registration without reusing the previous
     * visitor's document, live photo, category, or payment state.
     */
    public function startNew(Request $request)
    {
        $request->session()->forget([
            'verification',
            'didit_verification',
            'visitor_registration',
            'visitor_category',
            'appointment_registration_id',
        ]);

        return redirect()->route('visitor.create');
    }

    /**
     * Display the visitor registration form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function create(Request $request)
    {
        $type = $request->query('type');
        $validTypes = ['nic', 'driving_license', 'passport'];
        if (!in_array($type, $validTypes)) {
            return view('visitor.select_type');
        }
        $verification = $request->session()->get('verification', $request->session()->get('didit_verification', []));

        if (! is_array($verification) || blank(data_get($verification, 'session_id'))) {
            return redirect()->route('visitor.create')->withErrors([
                'verification' => 'Please complete identity verification before registration.',
            ]);
        }

        if (data_get($verification, 'face_verification_status') !== 'verified') {
            return redirect()->route('visitor.live_face');
        }

        $type = data_get($verification, 'document_type', $type);
        $category = $request->session()->get('visitor_category', []);
        $departments = Schema::hasTable('departments')
            ? Department::query()
                ->where('is_active', true)
                ->with(['people' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->orderBy('name')
                ->get()
            : $this->legacyDepartments();

        $appointment = $this->activeAppointment($request);

        return view('visitor.create', compact('type', 'verification', 'category', 'departments', 'appointment'));
    }

    /**
     * Begin the identity-verification journey from the unique link emailed
     * when an administrator schedules an appointment.
     */
    public function startAppointment(Request $request, VisitorAppointment $appointment, string $token)
    {
        if (! $appointment->hasValidRegistrationToken($token)
            || $appointment->status !== 'scheduled'
            || $appointment->registration_completed_at !== null) {
            abort(403, 'This appointment registration link is no longer available.');
        }

        $request->session()->forget([
            'verification',
            'didit_verification',
            'visitor_registration',
            'visitor_category',
        ]);
        $request->session()->put('appointment_registration_id', $appointment->id);

        return redirect()->route('visitor.upload_document');
    }

    /**
     * Display the document selection & upload/capture screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function showUploadDocument(Request $request)
    {
        $type = $request->query('type', 'nic');
        $validTypes = ['nic', 'driving_license', 'passport'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'nic';
        }

        return view('visitor.upload_document', compact('type'));
    }

    public function showLiveFaceCheck(Request $request)
    {
        $verification = $request->session()->get('verification', []);

        if (! is_array($verification) || blank(data_get($verification, 'session_id'))) {
            return redirect()->route('visitor.upload_document')->withErrors([
                'verification' => 'Upload and verify your identity document first.',
            ]);
        }

        if (data_get($verification, 'face_verification_status') === 'verified') {
            return redirect()->route('visitor.create', ['type' => data_get($verification, 'document_type', 'nic')]);
        }

        return view('visitor.live_face', ['type' => data_get($verification, 'document_type', 'nic')]);
    }

    /**
     * Validate the registration details and display the confirmation step.
     */
    public function confirm(Request $request)
    {
        $verification = $request->session()->get('verification', $request->session()->get('didit_verification', []));
        $category = $request->session()->get('visitor_category', []);
        $appointment = $this->activeAppointment($request);

        if (! is_array($verification) || blank(data_get($verification, 'session_id')) || data_get($verification, 'face_verification_status') !== 'verified') {
            return redirect()->route('visitor.create')->withErrors([
                'verification' => 'Complete the live camera identity check before registration.',
            ]);
        }

        if ($appointment) {
            $appointment->loadMissing(['department', 'personToMeet']);
            $request->merge([
                'department' => $appointment->department?->name,
                'person_to_meet' => $appointment->personToMeet?->name ?? 'Not specified',
                'company' => $appointment->company,
            ]);
        }

        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:180'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'mobile_number' => ['required', 'regex:/^[0-9]{9}$/'],
            'same_as_mobile' => 'nullable|boolean',
            'whatsapp_number' => ['nullable', 'regex:/^[0-9]{9}$/'],
            'occupation' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:150',
            'department' => 'required|string|max:150',
            'person_to_meet' => 'required|string|max:180',
            'visitor_count' => 'required|integer|min:1|max:20',
            'purpose' => 'required|string|max:1000',
        ], [
            'mobile_number.regex' => 'Enter a 9-digit number after +94.',
            'whatsapp_number.regex' => 'Enter a 9-digit number after +94.',
        ]);

        if ($appointment) {
            $department = $appointment->department;
            $personIsAvailable = $appointment->department_person_id === null
                || $appointment->personToMeet?->is_active === true;
        } elseif (Schema::hasTable('departments')) {
            $department = Department::query()
                ->where('name', $validated['department'])
                ->where('is_active', true)
                ->first();

            $personIsAvailable = $department?->people()
                ->where('name', $validated['person_to_meet'])
                ->where('is_active', true)
                ->exists();
        } else {
            $department = in_array($validated['department'], config('vms.departments', []), true);
            $personIsAvailable = in_array($validated['person_to_meet'], config('vms.people_to_meet', []), true);
        }

        if (! $department || ! $personIsAvailable) {
            throw ValidationException::withMessages([
                'person_to_meet' => 'Select a person available in the selected department.',
            ]);
        }
        $recordedName = trim((string) (
            data_get($verification, 'full_name')
            ?: data_get($verification, 'full_name_latin')
        ));
        $recordedNameIsReliable = $this->isReliableIdentityName($recordedName);
        $verifiedName = $recordedNameIsReliable
            ? $recordedName
            : trim((string) (data_get($validated, 'full_name') ?: $recordedName));
        $verifiedDocumentNumber = strtoupper((string) preg_replace(
            '/\s+/',
            '',
            (string) (data_get($verification, 'document_number') ?: data_get($validated, 'document_number'))
        ));

        if (blank($verifiedName) || blank($verifiedDocumentNumber)) {
            return redirect()
                ->route('visitor.create', ['type' => data_get($verification, 'document_type', 'nic')])
                ->withInput()
                ->withErrors([
                    'verification' => 'Enter the full name and NIC / ID number before submitting the visit request.',
                ]);
        }

        if (data_get($verification, 'document_type') === 'nic'
            && VerifiedVisitor::hasSubmittedNicRegistration(
                $verifiedDocumentNumber,
                data_get($verification, 'verification_id', data_get($verification, 'session_id'))
            )) {
            return redirect()
                ->route('visitor.create', ['type' => 'nic'])
                ->withInput()
                ->withErrors([
                    'verification' => 'This NIC is already registered and cannot be used to register again.',
                ]);
        }

        $details = array_merge($validated, [
            'verification_id' => data_get($verification, 'verification_id', data_get($verification, 'session_id')),
            'didit_session_id' => data_get($verification, 'verification_id', data_get($verification, 'session_id')),
            'document_type' => data_get($verification, 'document_type', 'nic'),
            'full_name' => $verifiedName,
            'full_name_latin' => $recordedNameIsReliable
                ? (data_get($verification, 'full_name_latin') ?: $verifiedName)
                : $verifiedName,
            'document_number' => $verifiedDocumentNumber,
            'address' => data_get($verification, 'address'),
            'address_latin' => data_get($verification, 'address_latin', data_get($verification, 'address')),
            'photo_url' => data_get($verification, 'photo_url')
                ?: route('visitor.session_photo', ['type' => data_get($verification, 'selfie_path') ? 'selfie' : 'photo']),
            'photo_path' => data_get($verification, 'photo_path'),
            'photo_mime' => data_get($verification, 'photo_mime'),
            'back_photo_path' => data_get($verification, 'back_photo_path'),
            'back_photo_mime' => data_get($verification, 'back_photo_mime'),
            'selfie_path' => data_get($verification, 'selfie_path'),
            'selfie_mime' => data_get($verification, 'selfie_mime'),
            'face_verification_status' => data_get($verification, 'face_verification_status'),
            'face_match_score' => data_get($verification, 'face_match_score'),
            'face_detection_confidence' => data_get($verification, 'face_detection_confidence'),
            'face_verified_at' => data_get($verification, 'face_verified_at'),
            'face_provider' => data_get($verification, 'face_provider'),
            'ocr_provider' => data_get($verification, 'provider'),
            'identity_reviewed_at' => now()->toIso8601String(),
            'verified_at' => data_get($verification, 'verified_at'),
            'whatsapp_number' => $request->boolean('same_as_mobile')
                ? $validated['mobile_number']
                : ($validated['whatsapp_number'] ?? $validated['mobile_number']),
            'department' => $validated['department'] ?? ($validated['company'] ?? 'Not specified'),
            'person_to_meet' => $validated['person_to_meet'] ?? ($validated['occupation'] ?? 'Not specified'),
            'visitor_count' => (int) ($validated['visitor_count'] ?? 1),
            'company' => $validated['company'] ?? ($validated['department'] ?? null),
            'occupation' => $validated['occupation'] ?? 'Visitor',
            'expected_gate' => config('vms.expected_gate', 'Main Gate'),
            'approval_status' => 'pending',
            'approval_requested_at' => now()->toIso8601String(),
            'payment_status' => 'not_required',
            'registration_status' => 'approval_pending',
            'category' => data_get($category, 'name', 'Not assigned'),
            'entrance_fee' => data_get($category, 'entrance_fee'),
            'purpose' => data_get($validated, 'purpose'),
            'visitor_appointment_id' => $appointment?->id,
        ]);

        $request->session()->put('visitor_registration', $details);
        $visitor = $this->persistVerifiedVisitor($details, [
            'payment_method' => null,
            'payment_status' => 'not_required',
            'registration_status' => 'approval_pending',
        ]);
        $request->session()->put('visitor_registration.record_id', $visitor->id);

        if ($appointment) {
            $appointment->update([
                'visitor_count' => $validated['visitor_count'],
                'purpose' => $validated['purpose'],
                'registration_completed_at' => now(),
            ]);
        }

        return view('visitor.confirm', compact('details'));
    }

    /**
     * Serve temporary session photos for visitor confirmation view.
     */
    public function sessionPhoto(Request $request, string $type = 'selfie')
    {
        $verification = $request->session()->get('verification', []);
        $registration = $request->session()->get('visitor_registration', []);

        $pathKey = in_array($type, ['selfie', 'photo', 'back_photo']) ? $type.'_path' : 'selfie_path';
        $mimeKey = in_array($type, ['selfie', 'photo', 'back_photo']) ? $type.'_mime' : 'selfie_mime';

        $path = data_get($registration, $pathKey, data_get($verification, $pathKey));
        $mime = data_get($registration, $mimeKey, data_get($verification, $mimeKey, 'image/jpeg'));

        // Fallback if selfie path is empty
        if (blank($path) && $type === 'selfie') {
            $path = data_get($registration, 'photo_path', data_get($verification, 'photo_path'));
            $mime = data_get($registration, 'photo_mime', data_get($verification, 'photo_mime', 'image/jpeg'));
        }

        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime ?: 'image/jpeg',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Store the selected payment method and route to the appropriate payment step.
     */
    public function selectPaymentMethod(Request $request)
    {
        if (! $request->session()->has('visitor_registration')) {
            return redirect()->route('visitor.create');
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:visa_master,amex,cash',
        ]);

        $request->session()->put('visitor_registration.payment_method', $validated['payment_method']);

        $details = $request->session()->get('visitor_registration');
        $paymentStatus = $validated['payment_method'] === 'cash' ? 'cash_pending' : 'card_pending';
        $visitor = $this->persistVerifiedVisitor($details, [
            'payment_method' => $validated['payment_method'],
            'payment_status' => $paymentStatus,
        ]);
        $request->session()->put('visitor_registration.record_id', $visitor->id);

        return $validated['payment_method'] === 'cash'
            ? redirect()->route('visitor.payment.cash')
            : redirect()->route('visitor.payment.card');
    }

    /** Display the card gateway hand-off screen. */
    public function cardGateway(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || ! in_array(data_get($details, 'payment_method'), ['visa_master', 'amex'], true)) {
            return redirect()->route('visitor.create');
        }

        return view('visitor.payment.card', compact('details'));
    }

    /** Display the cash payment confirmation screen. */
    public function cashConfirmation(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || data_get($details, 'payment_method') !== 'cash') {
            return redirect()->route('visitor.create');
        }

        // Cash payments are completed by reception. Once the admin marks this
        // record paid, send the visitor straight to the badge on the next poll.
        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if ($visitor && $visitor->payment_status === 'paid') {
            $paymentReference = data_get($details, 'payment_reference')
                ?: 'VMS-'.now()->format('Ymd').'-'.str_pad((string) $visitor->id, 6, '0', STR_PAD_LEFT);

            $request->session()->put('visitor_registration.payment_reference', $paymentReference);
            $request->session()->put('visitor_registration.payment_status', 'paid');

            return redirect()->route('visitor.thank-you');
        }

        return view('visitor.payment.cash', compact('details'));
    }

    /**
     * Record a successful payment hand-off and continue to the printable badge.
     */
    public function confirmPayment(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || blank(data_get($details, 'payment_method'))) {
            return redirect()->route('visitor.create');
        }

        if (data_get($details, 'payment_method') === 'cash') {
            return redirect()->route('visitor.payment.cash');
        }

        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if (! $visitor) {
            return redirect()->route('visitor.create')->withErrors([
                'registration' => 'Your registration session has expired. Please register again.',
            ]);
        }

        $paymentReference = data_get($details, 'payment_reference')
            ?: 'VMS-'.now()->format('Ymd').'-'.str_pad((string) $visitor->id, 6, '0', STR_PAD_LEFT);

        $visitor->update([
            'payment_status' => 'paid',
            'registration_status' => 'registered',
        ]);

        $request->session()->put('visitor_registration.payment_reference', $paymentReference);
        $request->session()->put('visitor_registration.payment_status', 'paid');

        return redirect()->route('visitor.thank-you');
    }

    /** Display the final visitor badge after payment confirmation. */
    public function thankYou(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || data_get($details, 'payment_status') !== 'paid') {
            return redirect()->route('visitor.create');
        }

        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if (! $visitor || $visitor->payment_status !== 'paid') {
            return redirect()->route('visitor.create');
        }

        $eventName = config('vms.event_name');
        $paymentReference = data_get($details, 'payment_reference');
        $qrPayload = (string) ($visitor->verification_id ?: $paymentReference ?: Str::uuid());
        $qrCode = QrCode::format('svg')
            ->size(220)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($qrPayload);

        return view('visitor.thank_you', compact(
            'details',
            'eventName',
            'paymentReference',
            'qrCode',
            'qrPayload'
        ));
    }

    /**
     * Display the visitors list.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function list()
    {
        $visitors = Visitor::all();
        return view('visitor.list', compact('visitors'));
    }

    /**
     * Store a newly created visitor in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'purpose' => 'required',
        ]);

        $docType = $request->input('document_type');
        $docNum = $request->input('document_number');
        $purpose = $request->purpose;
        if ($docType && $docNum) {
            $typeLabel = strtoupper(str_replace('_', ' ', $docType));
            $purpose = "[{$typeLabel}: {$docNum}] " . $purpose;
        }

        Visitor::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'vehicle' => $request->vehicle ?? null,
            'purpose' => $purpose,
            'checkin_status' => true,
        ]);

        return redirect()->route('visitor.create', ['type' => $docType])->with('success', 'Visitor registered successfully!');
    }

    /**
     * Update the check-in status of a visitor to false (checkout).
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function checkout(Request $request, $id)
    {
        $visitor = Visitor::findOrFail($id);

        $visitor->checkin_status = false;
        $visitor->save();

        return redirect()->route('visitor.list')->with('success', 'Visitor check out successfully!');
    }

    private function persistVerifiedVisitor(array $details, array $overrides = []): VerifiedVisitor
    {
        $verificationId = data_get($details, 'verification_id', data_get($details, 'didit_session_id', data_get($details, 'session_id')))
            ?: (string) Str::uuid();
        $values = array_merge([
            'document_type' => data_get($details, 'document_type'),
            'document_number' => data_get($details, 'document_number'),
            'full_name' => data_get($details, 'full_name'),
            'full_name_latin' => data_get($details, 'full_name_latin'),
            'address' => data_get($details, 'address'),
            'address_latin' => data_get($details, 'address_latin'),
            'mobile_number' => '+94'.data_get($details, 'mobile_number'),
            'whatsapp_number' => '+94'.data_get($details, 'whatsapp_number'),
            'occupation' => data_get($details, 'occupation'),
            'company' => data_get($details, 'company'),
            'department' => data_get($details, 'department'),
            'person_to_meet' => data_get($details, 'person_to_meet'),
            'visitor_count' => data_get($details, 'visitor_count', 1),
            'expected_gate' => data_get($details, 'expected_gate', config('vms.expected_gate', 'Main Gate')),
            'approval_status' => data_get($details, 'approval_status', 'pending'),
            'approval_requested_at' => data_get($details, 'approval_requested_at', now()),
            'photo_url' => data_get($details, 'photo_url'),
            'photo_path' => data_get($details, 'photo_path'),
            'photo_mime' => data_get($details, 'photo_mime'),
            'back_photo_path' => data_get($details, 'back_photo_path'),
            'back_photo_mime' => data_get($details, 'back_photo_mime'),
            'selfie_path' => data_get($details, 'selfie_path'),
            'selfie_mime' => data_get($details, 'selfie_mime'),
            'face_verification_status' => data_get($details, 'face_verification_status', 'pending'),
            'face_match_score' => data_get($details, 'face_match_score'),
            'face_detection_confidence' => data_get($details, 'face_detection_confidence'),
            'face_verified_at' => data_get($details, 'face_verified_at'),
            'face_provider' => data_get($details, 'face_provider'),
            'ocr_provider' => data_get($details, 'ocr_provider'),
            'identity_reviewed_at' => data_get($details, 'identity_reviewed_at', now()),
            'category' => data_get($details, 'category'),
            'entrance_fee' => data_get($details, 'entrance_fee'),
            'purpose' => data_get($details, 'purpose'),
            'visitor_appointment_id' => data_get($details, 'visitor_appointment_id'),
            'registration_status' => 'payment_pending',
            'verified_at' => data_get($details, 'verified_at', now()),
        ], $overrides);

        if (Schema::hasColumn('verified_visitors', 'didit_session_id')) {
            $values['didit_session_id'] = $verificationId;
        }

        return VerifiedVisitor::updateOrCreate(
            ['verification_id' => $verificationId],
            $values
        );
    }

    private function isReliableIdentityName(string $name): bool
    {
        $words = preg_split('/\s+/', strtoupper(trim($name)), -1, PREG_SPLIT_NO_EMPTY);
        $letterCounts = array_map(
            fn ($word) => strlen((string) preg_replace('/[^A-Z]/', '', $word)),
            $words
        );

        return count($words) >= 2
            && array_sum($letterCounts) >= 6
            && max($letterCounts ?: [0]) >= 3;
    }

    private function activeAppointment(Request $request): ?VisitorAppointment
    {
        $appointmentId = $request->session()->get('appointment_registration_id');

        if (! $appointmentId) {
            return null;
        }

        return VisitorAppointment::query()
            ->with(['department', 'personToMeet'])
            ->whereKey($appointmentId)
            ->where('status', 'scheduled')
            ->whereNull('registration_completed_at')
            ->first();
    }

    /**
     * Keep the registration form usable when an older installation has not
     * run the directory migration yet.
     */
    private function legacyDepartments()
    {
        $peopleByDepartment = [
            'Finance Department' => ['Ms. Nirosha Fernando'],
            'Human Resources' => ['Mr. Kasun Perera'],
            'Information Technology' => ['Ms. Amaya Silva'],
            'Operations Department' => ['Mr. Dinesh Jayawardena'],
        ];

        return collect(config('vms.departments', []))->map(function (string $name) use ($peopleByDepartment) {
            $department = new Department(['name' => $name, 'is_active' => true]);
            $people = collect($peopleByDepartment[$name] ?? [])->map(
                fn (string $person) => new \App\Models\DepartmentPerson(['name' => $person, 'is_active' => true])
            );

            return $department->setRelation('people', $people);
        });
    }
}
