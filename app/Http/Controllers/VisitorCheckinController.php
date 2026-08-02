<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;
use App\Services\LocalFaceVerificationService;
use App\Services\TesseractOcrService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisitorCheckinController extends Controller
{
    /**
     * Verify identity document using Local Open-Source Tesseract OCR (with fallback).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\TesseractOcrService  $tesseract
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyVision(
        Request $request,
        TesseractOcrService $tesseract,
        LocalFaceVerificationService $faceVerifier
    )
    {
        $request->validate([
            'document_type' => 'required|in:nic,driving_license,passport',
            'document_front_image' => 'required|file|image|mimes:jpeg,png,jpg,webp|max:10240',
            'document_back_image' => 'required_unless:document_type,passport|nullable|file|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $docType = $request->string('document_type')->toString();
        $file = $request->file('document_front_image');
        $backFile = $request->file('document_back_image');

        $imageBytes = file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $backImageBytes = $backFile ? file_get_contents($backFile->getRealPath()) : null;

        $rawOcrText = '';
        $ocrProvider = 'tesseract_ocr';

        // 1. Run local Open-Source Tesseract OCR
        $frontOcr = $tesseract->extractText($file->getRealPath());
        $backOcr = $backFile ? $tesseract->extractText($backFile->getRealPath()) : '';

        if (filled($frontOcr) || filled($backOcr)) {
            $rawOcrText = trim($frontOcr."\n".$backOcr);
            $ocrProvider = 'local_tesseract_engine';
        }

        // 2. If Tesseract binary is not installed yet on PATH, attempt Google Vision if credentials exist, else use smart local extraction
        if (blank($rawOcrText)) {
            $accessToken = $this->getAccessToken();
            $apiKey = config('services.google_vision.api_key');

            if (filled($accessToken) || filled($apiKey)) {
                try {
                    $http = Http::acceptJson()->connectTimeout(5)->timeout(15);
                    $http = $this->withGoogleCertificate($http);
                    $url = 'https://vision.googleapis.com/v1/images:annotate';
                    if (filled($accessToken)) {
                        $http = $http->withToken($accessToken);
                    } else {
                        $url .= "?key={$apiKey}";
                    }

                    $visionRequests = [[
                        'image' => ['content' => base64_encode($imageBytes)],
                        'features' => [
                            ['type' => 'DOCUMENT_TEXT_DETECTION'],
                            ['type' => 'FACE_DETECTION', 'maxResults' => 2],
                        ],
                    ]];
                    if ($backImageBytes !== null) {
                        $visionRequests[] = [
                            'image' => ['content' => base64_encode($backImageBytes)],
                            'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                        ];
                    }

                    $response = $http->post($url, ['requests' => $visionRequests]);

                    if ($response->successful()) {
                        $rawOcrText = (string) data_get($response->json(), 'responses.0.fullTextAnnotation.text', '');
                        if (blank($rawOcrText)) {
                            $rawOcrText = (string) data_get($response->json(), 'responses.0.textAnnotations.0.description', '');
                        }
                        if ($backImageBytes !== null) {
                            $backOcrText = (string) data_get($response->json(), 'responses.1.fullTextAnnotation.text', '');
                            $rawOcrText = trim($rawOcrText."\n".$backOcrText);
                        }
                        $ocrProvider = 'google_vision';
                    }
                } catch (\Throwable $e) {
                    logger()->info('Google Vision fallback skipped: '.$e->getMessage());
                }
            }
        }

        if (blank($rawOcrText)) {
            if ((! $tesseract->findExecutable() || ! function_exists('imagecreatefromstring')) && blank($accessToken) && blank($apiKey)) {
                return response()->json([
                    'success' => false,
                    'error' => 'OCR is not configured on this server. Install Tesseract and enable PHP GD, or configure GOOGLE_VISION_API_KEY / GOOGLE_APPLICATION_CREDENTIALS before verifying documents.',
                    'code' => 'ocr_not_configured',
                ], 503);
            }

            return response()->json([
                'success' => false,
                'error' => 'No readable identity details were found. Retake the document photo in even lighting and keep all text in focus.',
            ], 422);
        }

        // Parse the text extracted from the identity document.
        $parsed = $this->parseDocumentText($rawOcrText, $docType);
        if ($ocrProvider === 'local_tesseract_engine') {
            $frontParsed = $this->parseDocumentText($frontOcr, $docType);
            $backParsed = $this->parseDocumentText($backOcr, $docType);
            $parsed = [
                'document_number' => $frontParsed['document_number'] ?: $backParsed['document_number'],
                'full_name' => $frontParsed['full_name'],
                'full_name_latin' => $frontParsed['full_name_latin'],
                'address' => $backParsed['address'] ?: $frontParsed['address'],
                'address_latin' => $backParsed['address_latin'] ?: $frontParsed['address_latin'],
            ];
        }

        if (blank($parsed['document_number']) && blank($parsed['full_name'])) {
            return response()->json([
                'success' => false,
                'error' => 'The identity number and name could not be read. Retake the document photo closer and in sharper focus.',
            ], 422);
        }

        if ($docType === 'nic' && VerifiedVisitor::hasSubmittedNicRegistration($parsed['document_number'])) {
            return response()->json([
                'success' => false,
                'code' => 'nic_already_registered',
                'error' => 'This NIC is already registered and cannot be used to register again.',
            ], 422);
        }

        try {
            $documentFace = $faceVerifier->inspectDocument($file->getRealPath());
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => 'Local face verification is temporarily unavailable. Please contact reception.',
            ], 503);
        }

        if (! data_get($documentFace, 'success')) {
            return response()->json([
                'success' => false,
                'error' => data_get($documentFace, 'message', 'No clear portrait was detected on the identity document.'),
                'code' => data_get($documentFace, 'code'),
            ], 422);
        }

        $verificationId = (string) Str::uuid();
        $photoPath = "verified-visitors/{$verificationId}.{$extension}";
        Storage::disk('local')->put($photoPath, $imageBytes);

        $backPhotoPath = null;
        $backPhotoMime = null;
        if ($backFile && $backImageBytes !== null) {
            $backExtension = $backFile->getClientOriginalExtension() ?: 'jpg';
            $backPhotoPath = "verified-visitors/{$verificationId}-back.{$backExtension}";
            $backPhotoMime = $backFile->getMimeType() ?: 'image/jpeg';
            Storage::disk('local')->put($backPhotoPath, $backImageBytes);
        }

        $verification = [
            'session_id' => $verificationId,
            'verification_id' => $verificationId,
            'document_type' => $docType,
            'verified_at' => now()->toIso8601String(),
            'full_name' => $parsed['full_name'],
            'full_name_latin' => $parsed['full_name_latin'],
            'document_number' => $parsed['document_number'],
            'address' => $parsed['address'],
            'address_latin' => $parsed['address_latin'],
            'photo_url' => route('visitor.session_photo', ['type' => 'photo']),
            'photo_path' => $photoPath,
            'photo_mime' => $mime,
            'back_photo_path' => $backPhotoPath,
            'back_photo_mime' => $backPhotoMime,
            'ocr_text' => $rawOcrText,
            'provider' => $ocrProvider,
            'document_face_detected' => true,
            'document_face_confidence' => data_get($documentFace, 'detection_confidence'),
            'face_verification_status' => 'pending',
        ];

        // Store in session for registration form pre-filling
        $request->session()->put('verification', $verification);
        $request->session()->put('didit_verification', $verification);
        $request->session()->save();

        VerifiedVisitor::updateOrCreate(
            ['verification_id' => $verificationId],
            [
                'document_type' => $docType,
                'document_number' => $parsed['document_number'],
                'full_name' => $parsed['full_name'],
                'full_name_latin' => $parsed['full_name_latin'],
                'address' => $parsed['address'],
                'address_latin' => $parsed['address_latin'],
                'photo_url' => $verification['photo_url'],
                'photo_path' => $photoPath,
                'photo_mime' => $mime,
                'back_photo_path' => $backPhotoPath,
                'back_photo_mime' => $backPhotoMime,
                'ocr_provider' => $ocrProvider,
                'identity_reviewed_at' => now(),
                'face_verification_status' => 'pending',
                'approval_status' => 'draft',
                'registration_status' => 'identity_verified',
                'payment_status' => 'pending',
                'verified_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'verification_id' => $verificationId,
            'redirect_url' => route('visitor.live_face'),
            'data' => $verification,
        ]);
    }

    /** Capture and store a current visitor profile photo. */
    public function verifyLiveFace(Request $request)
    {
        $verification = $request->session()->get('verification', []);
        if (! is_array($verification) || blank(data_get($verification, 'photo_path'))) {
            return response()->json(['error' => 'The document verification session has expired. Please upload the document again.'], 422);
        }

        $request->validate([
            'selfie' => 'required|file|image|mimes:jpeg,png,jpg,webp|max:6144',
        ]);

        $file = $request->file('selfie');
        $bytes = file_get_contents($file->getRealPath());

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $selfiePath = 'verified-visitors/'.data_get($verification, 'verification_id').'-live.'.$extension;
        Storage::disk('local')->put($selfiePath, $bytes);
        if (! Storage::disk('local')->exists($selfiePath)) {
            return response()->json([
                'success' => false,
                'error' => 'The verified live photo could not be stored. Please try again.',
            ], 500);
        }

        $request->session()->put('verification', array_merge($verification, [
            'selfie_path' => $selfiePath,
            'selfie_mime' => $file->getMimeType() ?: 'image/jpeg',
            'face_verification_status' => 'verified',
            'face_match_score' => null,
            'face_detection_confidence' => null,
            'face_verified_at' => now()->toIso8601String(),
            'face_provider' => 'camera_capture',
        ]));
        $request->session()->save();
        VerifiedVisitor::where('verification_id', data_get($verification, 'verification_id'))->update([
            'selfie_path' => $selfiePath,
            'selfie_mime' => $file->getMimeType() ?: 'image/jpeg',
            'face_verification_status' => 'verified',
            'face_match_score' => null,
            'face_detection_confidence' => null,
            'face_verified_at' => now(),
            'face_provider' => 'camera_capture',
            'registration_status' => 'face_verified',
        ]);

        return response()->json([
            'success' => true,
            'redirect_url' => route('visitor.create', ['type' => data_get($verification, 'document_type', 'nic')]),
        ]);
    }

    /**
     * Generate OAuth2 access token for Google Cloud Service Account.
     */
    private function getAccessToken(): ?string
    {
        $credsPath = config('services.google_vision.credentials_path');

        if (blank($credsPath)) {
            return null;
        }

        if (! file_exists($credsPath)) {
            $candidate = base_path($credsPath);
            if (file_exists($candidate)) {
                $credsPath = $candidate;
            } else {
                return null;
            }
        }

        try {
            return Cache::remember('google_vision_access_token', 3300, function () use ($credsPath) {
                $json = json_decode(file_get_contents($credsPath), true);
                if (! is_array($json)) {
                    return null;
                }

                $clientEmail = data_get($json, 'client_email');
                $privateKey = data_get($json, 'private_key');
                $tokenUri = data_get($json, 'token_uri', 'https://oauth2.googleapis.com/token');

                if (blank($clientEmail) || blank($privateKey)) {
                    return null;
                }

                $now = time();
                $header = ['alg' => 'RS256', 'typ' => 'JWT'];
                $payload = [
                    'iss' => $clientEmail,
                    'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                    'aud' => $tokenUri,
                    'exp' => $now + 3600,
                    'iat' => $now,
                ];

                $base64UrlHeader = Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
                $base64UrlPayload = Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

                $signatureInput = "{$base64UrlHeader}.{$base64UrlPayload}";
                $signature = '';

                if (! openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
                    return null;
                }

                $base64UrlSignature = Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
                $jwt = "{$signatureInput}.{$base64UrlSignature}";

                $http = $this->withGoogleCertificate(Http::asForm());
                $response = $http->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                return $response->successful() ? $response->json('access_token') : null;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function withGoogleCertificate($http)
    {
        $caBundle = config('services.google_vision.ca_bundle');
        if (filled($caBundle) && file_exists($caBundle)) {
            return $http->withOptions(['verify' => $caBundle]);
        }

        return $http;
    }

    private function parseDocumentText(string $ocrText, string $docType): array
    {
        $lines = collect(explode("\n", $ocrText))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => filled($line))
            ->values();

        $docNumber = $this->extractDocumentNumber($ocrText, $lines, $docType);
        $fullName = $this->extractName($lines);
        $address = $this->extractAddress($lines);

        $sinhalaName = $this->containsSinhala($fullName) ? $fullName : null;
        $latinName = $this->containsSinhala($fullName) ? $this->extractLatinName($lines) : $fullName;

        $sinhalaAddress = $this->containsSinhala($address) ? $address : null;
        $latinAddress = $this->containsSinhala($address) ? $this->extractLatinAddress($lines) : $address;

        return [
            'document_number' => $docNumber,
            'full_name' => $sinhalaName ?: $latinName,
            'full_name_latin' => $latinName,
            'address' => $sinhalaAddress ?: $latinAddress,
            'address_latin' => $latinAddress,
        ];
    }

    private function extractDocumentNumber(string $fullText, $lines, string $docType): string
    {
        if (preg_match('/\b(19\d{10}|20\d{10}|\d{9}[VXvx])\b/', $fullText, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\b([A-Z]{1,2}\d{7,8})\b/', $fullText, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\b([A-Z]\d{7})\b/', $fullText, $matches)) {
            return strtoupper($matches[1]);
        }

        foreach ($lines as $line) {
            if (preg_match('/(?:NO|NUM|ID|NIC|PASSPORT)[:\.\s]*([A-Z0-9]{7,12})/i', $line, $matches)) {
                return strtoupper($matches[1]);
            }
        }

        return '';
    }

    private function extractName($lines): string
    {
        $nameCandidates = [];
        $addressContext = $lines
            ->filter(fn ($line) => preg_match('/\d{1,4}\s*[\/-]\s*\d{1,4}/', $line))
            ->map(fn ($line) => strtoupper((string) preg_replace('/[^A-Z ]/', '', $line)))
            ->implode(' ');
        foreach ($lines as $line) {
            if (preg_match('/(?:NAME|FULL NAME|SPECIMEN|සම්පූර්ණ නම)[:\.\s]*(.+)/i', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        foreach ($lines as $line) {
            if ($this->containsSinhala($line) && mb_strlen($line) > 3) {
                return $line;
            }
        }

        foreach ($lines as $line) {
            // OCR often returns names in title case (for example, \"Nimal
            // Perera\"). Normalise first so those letters are not discarded.
            $candidate = trim((string) preg_replace('/[^A-Z .-]/', '', strtoupper($line)));
            $words = preg_split('/\s+/', $candidate, -1, PREG_SPLIT_NO_EMPTY);
            $nameLetterCounts = array_map(
                fn ($word) => strlen((string) preg_replace('/[^A-Z]/', '', $word)),
                $words
            );
            if (count($words) >= 2 && count($words) <= 7
                && min(array_map('strlen', $words)) >= 2
                && array_sum($nameLetterCounts) >= 6
                && max($nameLetterCounts) >= 3
                && preg_match('/^[A-Z]+(?:[ .-]+[A-Z]+)+$/', $candidate)
                && ! str_contains($addressContext, str_replace(['.', '-'], '', $candidate))
                && ! preg_match('/SRI LANKA|IDENTITY|NATIONAL|HOLDER|SIGNATURE|REPUBLIC|DEPARTMENT|DATE|PLACE|REGISTRATION|ROAD|STREET|MAWATHA|COLOMBO|KANDY|GALLE|JAFFNA/', $candidate)) {
                $nameCandidates[] = $candidate;
            }
        }

        usort($nameCandidates, fn ($a, $b) => strlen(preg_replace('/[^A-Z]/', '', $b)) <=> strlen(preg_replace('/[^A-Z]/', '', $a)));
        return $nameCandidates[0] ?? '';
    }

    private function extractLatinName($lines): string
    {
        foreach ($lines as $line) {
            $candidate = strtoupper(trim($line));
            if (! $this->containsSinhala($line) && preg_match('/^[A-Z\s]{4,40}$/', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function extractAddress($lines): string
    {
        foreach ($lines as $line) {
            if (preg_match('/(?:ADDRESS|ලිපිනය)[:\.\s]*(.+)/i', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        foreach ($lines as $line) {
            $clean = trim((string) preg_replace('/[^A-Z0-9,\/ .-]/', '', strtoupper($line)));
            if (preg_match('/^\d{1,4}[A-Z]?(?:[\/-]\d{1,4}[A-Z]?)?,?\s+[A-Z]{3,}(?:\s+[A-Z]{3,})+$/', $clean)) {
                return $clean;
            }
        }

        $addressParts = [];
        foreach ($lines as $line) {
            if (preg_match('/\b(road|street|mawatha|colombo|kandy|galle|jaffna|no|අංක|පාර|මාවත|කොළඹ)\b/i', $line)) {
                $addressParts[] = $line;
            }
        }

        if (! $addressParts) {
            foreach ($lines as $line) {
                $clean = trim((string) preg_replace('/[^A-Z0-9,\/ .-]/', '', strtoupper($line)));
                if (preg_match('/^\d{1,4}[A-Z]?(?:[\/-]\d{1,4}[A-Z]?)?,?\s+[A-Z]{3,}(?:\s+[A-Z]{3,})+$/', $clean)) {
                    $addressParts[] = $clean;
                }
            }
        }

        return implode(', ', array_unique($addressParts));
    }

    private function extractLatinAddress($lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            if (! $this->containsSinhala($line) && preg_match('/\b(road|street|mawatha|colombo|kandy|galle|jaffna|no)\b/i', $line)) {
                $parts[] = $line;
            }
        }

        return implode(', ', $parts);
    }

    private function containsSinhala(string $value): bool
    {
        return preg_match('/[\x{0D80}-\x{0DFF}]/u', $value) === 1;
    }
}
