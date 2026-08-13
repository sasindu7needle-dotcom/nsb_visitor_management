<?php

namespace App\Http\Controllers;

use App\Exceptions\GateScanException;
use App\Models\GateLog;
use App\Models\ReturningFaceVerification;
use App\Models\User;
use App\Models\VerifiedVisitor;
use App\Models\VisitorAppointment;
use App\Services\GateLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(GateLogService $gateLogService)
    {
        $withGateLogs = [
            'gateLogs' => fn ($query) => $query->orderBy('scanned_at')->orderBy('id'),
        ];
        $liveCounts = $this->liveCounts();
        $checkedOutToday = VerifiedVisitor::query()
            ->whereHas('gateLogs', fn ($query) => $query
                ->where('direction', 'out')
                ->whereDate('scanned_at', today()));
        $stats = [
            'total' => VerifiedVisitor::count(),
            'today' => VerifiedVisitor::whereDate('verified_at', today())->count(),
            'checked_in' => $liveCounts['inside'],
            'checked_out' => (clone $checkedOutToday)->count(),
        ];

        $statDetails = [
            'total' => [
                'title' => 'All Visitors',
                'time_label' => 'Registered',
                'visitors' => VerifiedVisitor::query()->with($withGateLogs)->latest()->limit(50)->get(),
            ],
            'today' => [
                'title' => 'Arrivals Today',
                'time_label' => 'Registered',
                'visitors' => VerifiedVisitor::query()->with($withGateLogs)->whereDate('verified_at', today())->latest()->limit(50)->get(),
            ],
            'inside' => [
                'title' => 'Visitors Currently Inside',
                'time_label' => 'Checked in',
                'visitors' => $this->insideVisitorQuery()->with($withGateLogs)->latest('checked_in_at')->limit(50)->get(),
            ],
            'checked_out' => [
                'title' => 'Visitors Checked Out Today',
                'time_label' => 'Checked out',
                'visitors' => $checkedOutToday->with($withGateLogs)->latest('checked_out_at')->limit(50)->get(),
            ],
        ];
        $profileVisitors = collect($statDetails)
            ->pluck('visitors')
            ->flatten()
            ->unique('id')
            ->values();
        $profileVisitors->each(fn ($visitor) => $visitor->setAttribute(
            'activity_rows',
            $gateLogService->activityRows($visitor->gateLogs)
        ));

        $recentVisitors = VerifiedVisitor::with(['gateLogs' => fn ($query) => $query->latest('scanned_at')->latest('id')])
            ->latest()
            ->limit(8)
            ->get();
        $pendingVisitors = VerifiedVisitor::query()
            ->where('approval_status', 'pending')
            ->whereNull('visitor_appointment_id')
            ->orderBy('approval_requested_at')
            ->orderBy('id')
            ->get();
        $returningFaceChecks = ReturningFaceVerification::query()
            ->with('visitor')
            ->latest('checked_at')
            ->limit(8)
            ->get();
        $returningFaceCheckCount = ReturningFaceVerification::count();

        return view('admin.dashboard', compact(
            'stats',
            'statDetails',
            'profileVisitors',
            'recentVisitors',
            'pendingVisitors',
            'returningFaceChecks',
            'returningFaceCheckCount',
        ));
    }

    public function counts(): JsonResponse
    {
        return response()->json($this->liveCounts() + [
            'pending_approvals' => VerifiedVisitor::where('approval_status', 'pending')
                ->whereNull('visitor_appointment_id')
                ->count(),
            'returning_face_checks' => ReturningFaceVerification::count(),
        ]);
    }

    public function decideVisitorRequest(
        Request $request,
        VerifiedVisitor $visitor,
        GateLogService $gateLogService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:allow,reject'],
            'pass_issued' => ['nullable', 'boolean'],
            'visitor_pass_number' => ['nullable', 'string', 'max:50', 'required_if:pass_issued,1'],
            'return_to' => ['nullable', 'in:dashboard,appointment'],
        ]);

        if ($visitor->approval_status !== 'pending') {
            return back()->withErrors([
                'visitor_request' => 'This visitor request has already been reviewed.',
            ]);
        }

        $adminUsername = (string) $request->session()->get('admin_username');
        $adminId = auth()->id() ?: User::query()
            ->where('name', $adminUsername)
            ->orWhere('email', $adminUsername)
            ->value('id');
        $approved = $validated['decision'] === 'allow';

        try {
            DB::transaction(function () use ($visitor, $approved, $request, $validated, $adminId, $gateLogService) {
                $visitor->update([
                    'approval_status' => $approved ? 'approved' : 'rejected',
                    'approved_at' => now(),
                    'approved_by' => $adminId,
                    'is_blocked' => ! $approved,
                    'visitor_pass_number' => $approved && $request->boolean('pass_issued')
                        ? trim((string) $validated['visitor_pass_number'])
                        : null,
                    'visitor_pass_issued_at' => $approved && $request->boolean('pass_issued') ? now() : null,
                    'visitor_pass_returned_at' => null,
                ]);

                // Allowing entry is the check-in action. It records arrival and
                // immediately includes the visitor in the inside count.
                if ($approved) {
                    $gateLogService->scan(
                        (string) ($visitor->verification_id ?: $visitor->id),
                        'ADMIN',
                        $adminId,
                        'in'
                    );
                }
            }, 3);
        } catch (GateScanException $exception) {
            return back()->withErrors([
                'visitor_request' => $exception->getMessage(),
            ]);
        }

        $redirect = redirect();
        if (($validated['return_to'] ?? null) === 'appointment' && $visitor->visitor_appointment_id) {
            return $redirect->route('admin.appointments.show', $visitor->visitor_appointment_id)
                ->with('status', $approved
                    ? "{$visitor->full_name}'s visit has been allowed and marked as inside."
                    : "{$visitor->full_name}'s visit has been rejected.");
        }

        return $redirect->route('admin.dashboard')
            ->with('status', $approved
                ? "{$visitor->full_name}'s visit has been allowed and marked as inside."
                : "{$visitor->full_name}'s visit has been rejected.");
    }

    /** Display the security approval card for a visitor registered from an appointment email. */
    public function appointmentVisitorReview(VisitorAppointment $appointment)
    {
        $appointment->load(['department', 'personToMeet', 'registeredVisitor']);

        abort_unless($appointment->registeredVisitor, 404);

        return view('admin.appointments.visitor-review', [
            'appointment' => $appointment,
            'visitor' => $appointment->registeredVisitor,
        ]);
    }

    public function markVisitorPassReturned(VerifiedVisitor $visitor): RedirectResponse
    {
        if (! $visitor->visitor_pass_issued_at || blank($visitor->visitor_pass_number)) {
            return back()->withErrors([
                'visitor_pass' => 'No issued visitor pass is recorded for this visitor.',
            ]);
        }

        if ($visitor->visitor_pass_returned_at) {
            return back()->withErrors([
                'visitor_pass' => 'This visitor pass has already been returned.',
            ]);
        }

        if ($visitor->checkin_status || ! $visitor->checked_out_at) {
            return back()->withErrors([
                'visitor_pass' => 'Check out the visitor before marking their pass as returned.',
            ]);
        }

        $visitor->update(['visitor_pass_returned_at' => now()]);

        return back()->with('status', "Visitor pass {$visitor->visitor_pass_number} was returned.");
    }

    private function liveCounts(): array
    {
        return [
            'inside' => $this->insideVisitorQuery()->count(),
        ];
    }

    private function insideVisitorQuery()
    {
        $latestLogIds = GateLog::query()->selectRaw('MAX(id)')->groupBy('visitor_id');

        return VerifiedVisitor::query()
            ->whereHas('gateLogs', fn ($query) => $query
                ->whereIn('id', $latestLogIds)
                ->where('direction', 'in'));
    }
}
