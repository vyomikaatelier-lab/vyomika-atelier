<?php



namespace App\Http\Controllers\Admin;



use App\Http\Controllers\Controller;

use App\Models\BlockedIdentity;

use App\Models\Lead;

use App\Models\LeadActivity;

use App\Models\User;

use App\Services\LeadContactHistoryService;

use App\Services\LeadScoringService;

use App\Support\EnquiryType;

use App\Support\LeadProtectionStatus;

use App\Support\LeadStatus;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;

use Symfony\Component\HttpFoundation\StreamedResponse;



class LeadAdminController extends Controller

{

    public function __construct(

        private LeadContactHistoryService $contactHistory,

        private LeadScoringService $scoring,

    ) {}



    public function index(Request $request)

    {

        $query = Lead::with('assignee')->latest();



        if ($request->filled('protection_status')) {

            $query->where('protection_status', $request->protection_status);

        }



        if ($request->filled('status')) {

            $query->where('status', $request->status);

        }



        if ($request->filled('enquiry_type')) {

            $query->where('enquiry_type', $request->enquiry_type);

        }



        if ($request->filled('type')) {

            $query->where('type', $request->type);

        }



        if ($request->filled('priority')) {

            $query->where('priority', $request->priority);

        }



        if ($request->filled('assigned_to')) {

            $query->where('assigned_to', $request->assigned_to);

        }



        if ($request->filled('source')) {

            $query->where('first_touch_source', 'like', '%' . $request->source . '%');

        }



        if ($request->filled('city')) {

            $query->where(function ($q) use ($request) {

                $q->whereJsonContains('metadata->city', $request->city)

                    ->orWhere('metadata->project_location', 'like', '%' . $request->city . '%');

            });

        }



        if ($request->boolean('verified')) {

            $query->where('protection_status', LeadProtectionStatus::VERIFIED);

        }



        if ($request->boolean('duplicate')) {

            $query->where('protection_status', LeadProtectionStatus::DUPLICATE);

        }



        if ($request->filled('score_min')) {

            $query->where('lead_score', '>=', (int) $request->score_min);

        }



        if ($request->filled('date_from')) {

            $query->whereDate('created_at', '>=', $request->date_from);

        }



        if ($request->filled('date_to')) {

            $query->whereDate('created_at', '<=', $request->date_to);

        }



        if ($request->filled('sales_queue') && $request->sales_queue === '1') {

            $query->where('enquiry_type', '!=', EnquiryType::VENDOR_MARKETING);

        }



        if ($request->get('follow_up') === 'overdue') {

            $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now());

        }



        if ($request->filled('q')) {

            $q = $request->string('q');

            $query->where(function ($builder) use ($q) {

                $builder->where('name', 'like', "%{$q}%")

                    ->orWhere('email', 'like', "%{$q}%")

                    ->orWhere('phone', 'like', "%{$q}%");

            });

        }



        $stats = [

            'new' => Lead::where('status', LeadStatus::NEW)->where('enquiry_type', '!=', EnquiryType::VENDOR_MARKETING)->count(),

            'verified' => Lead::where('status', LeadStatus::VERIFIED)->count(),

            'hot' => Lead::where('priority', 'hot')->count(),

            'qualified' => Lead::where('status', LeadStatus::QUALIFIED)->count(),

            'duplicates' => Lead::where('protection_status', LeadProtectionStatus::DUPLICATE)->count(),

            'vendor' => Lead::where('enquiry_type', EnquiryType::VENDOR_MARKETING)->count(),

            'spam' => Lead::where('protection_status', LeadProtectionStatus::SPAM_SUSPECTED)->count(),

            'overdue' => Lead::whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now())->count(),

            'quotation_sent' => Lead::where('status', LeadStatus::QUOTATION_SENT)->count(),

            'won' => Lead::where('status', LeadStatus::WON)->count(),

            'lost' => Lead::where('status', LeadStatus::LOST)->count(),

        ];



        $leads = $query->paginate(15)->withQueryString();

        $assignees = User::where('is_admin', true)->orderBy('name')->get();



        return view('admin.leads.index', compact('leads', 'stats', 'assignees'));

    }



    public function show(Lead $lead)

    {

        $lead->load(['duplicateOf', 'duplicates', 'assignee', 'activities.user']);

        $history = $this->contactHistory->forLead($lead);

        $assignees = User::where('is_admin', true)->orderBy('name')->get();



        return view('admin.leads.show', compact('lead', 'history', 'assignees'));

    }



    public function update(Request $request, Lead $lead)

    {

        $validated = $request->validate([

            'status' => 'required|in:' . implode(',', $lead->allowedStatuses()),

            'admin_notes' => 'nullable|string|max:5000',

            'internal_note' => 'nullable|string|max:5000',

            'assigned_to' => 'nullable|exists:users,id',

            'next_follow_up_at' => 'nullable|date',

            'last_contacted_at' => 'nullable|date',

            'expected_order_value' => 'nullable|numeric|min:0',

            'lost_reason' => 'nullable|string|max:255',

            'priority' => 'nullable|in:hot,high,medium,low',

        ]);



        $oldStatus = $lead->status;



        $lead->update(collect($validated)->except('internal_note')->filter(fn ($v) => $v !== null)->all());



        if (! empty($validated['internal_note'])) {

            $lead->appendInternalNote($validated['internal_note'], $request->user()?->id);

            LeadActivity::create([

                'lead_id' => $lead->id,

                'user_id' => $request->user()?->id,

                'activity_type' => 'note',

                'body' => $validated['internal_note'],

            ]);

        }



        if ($oldStatus !== $lead->status) {

            LeadActivity::create([

                'lead_id' => $lead->id,

                'user_id' => $request->user()?->id,

                'activity_type' => 'status_change',

                'body' => "Status changed from {$oldStatus} to {$lead->status}",

                'metadata' => ['from' => $oldStatus, 'to' => $lead->status],

            ]);

        }



        $recalc = $this->scoring->scoreExistingLead($lead->fresh());

        $lead->update([

            'lead_score' => $recalc['score'],

            'lead_score_reasons' => $recalc['reasons'],

            'risk_score' => max(0, min(100, $recalc['score'])),

            'risk_reasons' => $recalc['reasons'],

            'priority' => $validated['priority'] ?? $recalc['priority'],

        ]);



        return back()->with('success', 'Lead updated.');

    }



    public function markFalsePositive(Lead $lead)

    {

        $lead->update([

            'protection_status' => LeadProtectionStatus::VERIFIED,

            'status' => LeadStatus::VERIFIED,

            'false_positive_at' => now(),

            'notifications_suppressed' => false,

        ]);



        LeadActivity::create([

            'lead_id' => $lead->id,

            'activity_type' => 'restore',

            'body' => 'Marked as false positive / verified',

        ]);



        return back()->with('success', 'Lead marked as verified (false positive cleared).');

    }



    public function markQualified(Lead $lead)

    {

        $lead->update(['status' => LeadStatus::QUALIFIED]);

        LeadActivity::create(['lead_id' => $lead->id, 'activity_type' => 'status_change', 'body' => 'Marked qualified']);



        return back()->with('success', 'Lead marked as qualified.');

    }



    public function markVendor(Lead $lead)

    {

        $lead->update([

            'enquiry_type' => EnquiryType::VENDOR_MARKETING,

            'status' => LeadStatus::MARKETING_VENDOR,

            'protection_status' => LeadProtectionStatus::MARKETING_VENDOR,

        ]);



        return back()->with('success', 'Lead moved to vendor/marketing queue.');

    }



    public function markSpam(Lead $lead)

    {

        $lead->update([

            'status' => LeadStatus::SPAM_SUSPECTED,

            'protection_status' => LeadProtectionStatus::SPAM_SUSPECTED,

        ]);



        return back()->with('success', 'Lead marked as spam suspected.');

    }



    public function mergeDuplicate(Lead $lead)

    {

        if (! $lead->duplicate_of_id) {

            return back()->withErrors(['merge' => 'This lead is not flagged as a duplicate.']);

        }



        $original = Lead::find($lead->duplicate_of_id);

        if ($original) {

            $original->increment('duplicate_count');

            LeadActivity::create([

                'lead_id' => $original->id,

                'activity_type' => 'merge',

                'body' => 'Manually merged duplicate lead #' . $lead->id,

            ]);

        }



        return back()->with('success', 'Duplicate merge recorded on original lead.');

    }



    public function blockIdentity(Request $request, Lead $lead)

    {

        $validated = $request->validate([

            'identity_type' => 'required|in:email,phone,ip,email_domain',

            'reason' => 'nullable|string|max:500',

            'expires_at' => 'nullable|date|after:now',

        ]);



        $value = match ($validated['identity_type']) {

            'email' => $lead->email,

            'phone' => preg_replace('/\D/', '', (string) $lead->phone),

            'ip' => $lead->ip_fingerprint,

            'email_domain' => str_contains($lead->email, '@') ? substr($lead->email, strrpos($lead->email, '@') + 1) : null,

            default => null,

        };



        if (! filled($value)) {

            return back()->withErrors(['identity_type' => 'No value available to block for this lead.']);

        }



        BlockedIdentity::query()

            ->where('identity_type', $validated['identity_type'])

            ->where('value_hash', $validated['identity_type'] === 'email_domain'

                ? BlockedIdentity::hashValue((string) $value)

                : ($validated['identity_type'] === 'ip' ? (string) $value : BlockedIdentity::hashValue((string) $value)))

            ->where('is_active', true)

            ->update(['is_active' => false, 'lifted_at' => now()]);



        BlockedIdentity::create([

            'identity_type' => $validated['identity_type'],

            'value_hash' => $validated['identity_type'] === 'ip'

                ? (string) $value

                : BlockedIdentity::hashValue((string) $value),

            'value_hint' => $validated['identity_type'] === 'ip'

                ? substr((string) $value, 0, 12) . '…'

                : BlockedIdentity::hint((string) $value),

            'email_domain' => $validated['identity_type'] === 'email_domain' ? (string) $value : null,

            'reason' => $validated['reason'] ?? 'Blocked from admin panel',

            'blocked_by' => $request->user()?->id,

            'lead_id' => $lead->id,

            'is_active' => true,

            'expires_at' => $validated['expires_at'] ?? null,

        ]);



        $lead->update([

            'protection_status' => LeadProtectionStatus::BLOCKED,

            'status' => LeadStatus::BLOCKED,

        ]);



        LeadActivity::create([

            'lead_id' => $lead->id,

            'user_id' => $request->user()?->id,

            'activity_type' => 'block',

            'body' => 'Blocked ' . $validated['identity_type'],

        ]);



        return back()->with('success', 'Identity blocked.');

    }



    public function restore(Lead $lead)

    {

        $lead->update([

            'protection_status' => LeadProtectionStatus::NEEDS_VERIFICATION,

            'status' => LeadStatus::NEW,

            'restored_at' => now(),

            'notifications_suppressed' => false,

        ]);



        LeadActivity::create([

            'lead_id' => $lead->id,

            'activity_type' => 'restore',

            'body' => 'Lead restored for review',

        ]);



        return back()->with('success', 'Lead restored for review.');

    }



    public function destroy(Lead $lead)

    {

        $lead->delete();



        return redirect()->route('admin.leads.index')->with('success', 'Lead deleted.');

    }



    public function downloadAttachment(Lead $lead): StreamedResponse

    {

        $path = $lead->attachmentPath();

        abort_unless($path && Storage::disk('local')->exists($path), 404);



        return Storage::disk('local')->download($path, $lead->attachmentFilename());

    }

}


