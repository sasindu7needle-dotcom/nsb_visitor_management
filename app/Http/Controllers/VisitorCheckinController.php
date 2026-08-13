<?php

namespace App\Http\Controllers;

use App\Services\GeminiDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisitorCheckinController extends Controller
{
    /**
     * Read an uploaded document and pre-fill the registration form. No OCR
     * fallback or biometric verification is performed.
     */
    public function readDocument(Request $request, GeminiDocumentService $gemini)
    {
        @set_time_limit(120);

        $validated = $request->validate([
            'document_type' => ['required', 'in:nic,driving_license,passport'],
            'document_front_image' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'document_back_image' => ['required_if:document_type,nic', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        $type = $validated['document_type'];
        $front = $request->file('document_front_image');
        $back = $type === 'nic' ? $request->file('document_back_image') : null;

        try {
            $details = $gemini->extract(
                $front->getRealPath(),
                $front->getMimeType() ?: 'image/jpeg',
                $back?->getRealPath(),
                $back?->getMimeType(),
                $type,
            );
        } catch (\Throwable $exception) {
            Log::warning('Gemini visitor document extraction failed.', [
                'document_type' => $type,
                'exception_class' => $exception::class,
            ]);

            $notConfigured = str_contains(strtolower($exception->getMessage()), 'api key');
            return response()->json([
                'success' => false,
                'error' => $notConfigured
                    ? 'Gemini is not configured. Add GEMINI_API_KEY to the server environment.'
                    : 'Gemini could not read the document. Please use clear, glare-free photos and try again.',
            ], $notConfigured ? 503 : 422);
        }

        $registrationId = (string) Str::uuid();
        $frontPath = $this->storeDocument($front, $registrationId, 'front');
        $backPath = $back ? $this->storeDocument($back, $registrationId, 'back') : null;
        $sessionData = [
            'session_id' => $registrationId,
            'verification_id' => $registrationId,
            'document_type' => $type,
            'full_name' => $details['full_name'],
            'full_name_latin' => $details['full_name'],
            'document_number' => $details['document_number'],
            'address' => $details['address'],
            'address_latin' => $details['address'],
            'photo_path' => $frontPath['path'],
            'photo_mime' => $frontPath['mime'],
            'back_photo_path' => $backPath['path'] ?? null,
            'back_photo_mime' => $backPath['mime'] ?? null,
            'provider' => 'google_gemini',
            'document_read_at' => now()->toIso8601String(),
        ];

        $request->session()->put('verification', $sessionData);
        $request->session()->forget('didit_verification');

        return response()->json([
            'success' => true,
            'redirect_url' => route('visitor.capture_photo'),
            'data' => $sessionData,
        ]);
    }

    /** Save a visitor profile photo after their document details are read. */
    public function capturePhoto(Request $request)
    {
        $registration = $request->session()->get('verification', []);
        if (! is_array($registration) || blank(data_get($registration, 'verification_id'))) {
            return response()->json([
                'success' => false,
                'error' => 'Your document session has expired. Please upload the document again.',
            ], 422);
        }

        $request->validate([
            'selfie' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:6144'],
        ]);

        $file = $request->file('selfie');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $path = 'verified-visitors/'.data_get($registration, 'verification_id').'-face.'.$extension;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        $request->session()->put('verification', array_merge($registration, [
            'selfie_path' => $path,
            'selfie_mime' => $file->getMimeType() ?: 'image/jpeg',
            'photo_captured_at' => now()->toIso8601String(),
        ]));

        return response()->json([
            'success' => true,
            'redirect_url' => route('visitor.create', ['type' => data_get($registration, 'document_type', 'nic')]),
        ]);
    }

    private function storeDocument($file, string $registrationId, string $side): array
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $path = "verified-visitors/{$registrationId}-{$side}.{$extension}";
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return ['path' => $path, 'mime' => $file->getMimeType() ?: 'image/jpeg'];
    }
}
