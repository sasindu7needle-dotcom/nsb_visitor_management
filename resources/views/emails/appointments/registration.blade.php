<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Visitor registration</title></head>
<body style="margin:0;padding:24px;background:#f4f7fb;color:#172033;font-family:Arial,sans-serif;line-height:1.5">
    <main style="max-width:620px;margin:auto;padding:32px;background:#fff;border-radius:14px">
        <p style="margin:0 0 8px;color:#1769ed;font-size:12px;font-weight:bold;letter-spacing:.08em">NSB VISITOR MANAGEMENT</p>
        <h1 style="margin:0 0 16px;font-size:24px">Complete your visitor registration</h1>
        <p>Hello {{ $appointment->visitor_name }},</p>
        <p>Your visit has been scheduled. Use the secure button below to complete your self-registration. Upload a document so Gemini can prefill the registration information, then review it before submitting.</p>
        <table style="width:100%;margin:22px 0;border-collapse:collapse;background:#f8fafc">
            <tr><td style="padding:10px 14px;font-weight:bold">Reference</td><td style="padding:10px 14px">{{ $appointment->reference }}</td></tr>
            <tr><td style="padding:10px 14px;font-weight:bold">Arrival</td><td style="padding:10px 14px">{{ $appointment->scheduled_at->format('D, d M Y, h:i A') }}</td></tr>
            <tr><td style="padding:10px 14px;font-weight:bold">Department</td><td style="padding:10px 14px">{{ $appointment->department?->name }}</td></tr>
        </table>
        <p style="margin:26px 0"><a href="{{ $registrationUrl }}" style="display:inline-block;padding:13px 18px;background:#1769ed;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold">Complete registration</a></p>
        <p style="color:#64748b;font-size:12px">This personal link can be used once while the appointment is scheduled. If you did not expect this appointment, please contact NSB reception.</p>
    </main>
</body>
</html>
