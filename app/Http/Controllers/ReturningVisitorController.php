<?php

namespace App\Http\Controllers;

use App\Models\ReturningFaceVerification;
use App\Models\VerifiedVisitor;
use App\Services\LocalFaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReturningVisitorController extends Controller
{
    public function show()
    {
        return view('visitor.returning_face');
    }

    public function findByNic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nic_number' => ['required', 'string', 'max:20'],
        ]);

        $nic = $this->normaliseNic($validated['nic_number']);
        $visitor = VerifiedVisitor::query()
            ->whereRaw('UPPER(REPLACE(document_number, ?, ?)) = ?', [' ', '', $nic])
            ->whereNotNull('selfie_path')
            ->first();

        if (! $visitor) {
            return response()->json([
                'success' => false,
                'error' => 'No registered visitor with a stored registration photo was found for this NIC.',
            ], 404);
        }

        $request->session()->put('returning_visitor_id', $visitor->id);
        $request->session()->put('returning_visitor_nic', $nic);

        return response()->json([
            'success' => true,
            'visitor' => ['name' => $visitor->full_name ?: $visitor->full_name_latin ?: 'Registered visitor'],
        ]);
    }

    public function captureAndCompare(Request $request, LocalFaceVerificationService $faceVerifier): JsonResponse
    {
        $request->validate([
            'selfie' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:6144'],
        ]);

        $visitorId = $request->session()->get('returning_visitor_id');
        $nic = (string) $request->session()->get('returning_visitor_nic');
        $visitor = $visitorId ? VerifiedVisitor::find($visitorId) : null;

        if (! $visitor || blank($nic)) {
            return response()->json(['success' => false, 'error' => 'Enter and verify the NIC before capturing a face photo.'], 422);
        }

        if (blank($visitor->selfie_path) || ! Storage::disk('local')->exists($visitor->selfie_path)) {
            return response()->json(['success' => false, 'error' => 'The original registration face photo is unavailable for comparison.'], 422);
        }

        $file = $request->file('selfie');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $path = 'returning-face-checks/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        try {
            $comparison = $faceVerifier->compare(
                Storage::disk('local')->path($visitor->selfie_path),
                Storage::disk('local')->path($path),
            );
        } catch (\Throwable $exception) {
            report($exception);
            $comparison = [
                'success' => false,
                'code' => 'comparison_unavailable',
                'message' => 'Face comparison is temporarily unavailable. Security review is required.',
            ];
        }

        $isComparisonComplete = (bool) data_get($comparison, 'success');
        $status = $isComparisonComplete
            ? (data_get($comparison, 'matched') ? 'same' : 'different')
            : 'review_required';

        $check = ReturningFaceVerification::create([
            'visitor_id' => $visitor->id,
            'nic_number' => $nic,
            'photo_path' => $path,
            'photo_mime' => $file->getMimeType() ?: 'image/jpeg',
            'status' => $status,
            'match_score' => data_get($comparison, 'similarity_percent'),
            'detection_confidence' => data_get($comparison, 'live_detection_confidence'),
            'provider' => 'local_opencv_sface',
            'failure_code' => $isComparisonComplete ? null : data_get($comparison, 'code'),
            'message' => data_get($comparison, 'message'),
            'checked_at' => now(),
        ]);

        $request->session()->forget(['returning_visitor_id', 'returning_visitor_nic']);
        $redirectUrl = null;

        if ($status === 'same') {
            $documentType = in_array($visitor->document_type, ['nic', 'driving_license', 'passport'], true)
                ? $visitor->document_type
                : 'nic';
            $verificationId = $visitor->verification_id ?: (string) Str::uuid();

            // Reuse the verified identity and registration photo, but collect a
            // fresh visit request. VisitorController@confirm then sets this
            // visitor's approval status to pending for the security officer.
            $request->session()->forget(['visitor_registration', 'visitor_category']);
            $request->session()->put('verification', [
                'session_id' => $verificationId,
                'verification_id' => $verificationId,
                'document_type' => $documentType,
                'full_name' => $visitor->full_name,
                'full_name_latin' => $visitor->full_name_latin,
                'document_number' => $visitor->document_number,
                'address' => $visitor->address,
                'address_latin' => $visitor->address_latin,
                'photo_url' => $visitor->photo_url,
                'photo_path' => $visitor->photo_path,
                'photo_mime' => $visitor->photo_mime,
                'back_photo_path' => $visitor->back_photo_path,
                'back_photo_mime' => $visitor->back_photo_mime,
                'selfie_path' => $visitor->selfie_path,
                'selfie_mime' => $visitor->selfie_mime,
                'face_verification_status' => 'verified',
                'face_match_score' => $visitor->face_match_score,
                'face_detection_confidence' => $visitor->face_detection_confidence,
                'face_verified_at' => $visitor->face_verified_at?->toIso8601String(),
                'face_provider' => $visitor->face_provider,
                'provider' => $visitor->ocr_provider,
                'verified_at' => $visitor->verified_at?->toIso8601String(),
                'returning_face_check_id' => $check->id,
            ]);
            $request->session()->save();
            $redirectUrl = route('visitor.create', ['type' => $documentType]);
        }

        return response()->json([
            'success' => true,
            'status' => $status,
            'match_score' => $check->match_score,
            'redirect_url' => $redirectUrl,
            'message' => $status === 'same'
                ? 'Same face confirmed. Continue with your visit details for security approval.'
                : ($status === 'different'
                    ? 'Different face detected. Please wait for a security officer.'
                    : 'The image needs security review. Please wait for a security officer.'),
        ]);
    }

    private function normaliseNic(string $nic): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', trim($nic)));
    }
}
