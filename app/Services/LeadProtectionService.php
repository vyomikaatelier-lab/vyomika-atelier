<?php



namespace App\Services;



use App\Models\Lead;

use App\Models\LeadActivity;

use App\Support\EnquiryType;

use App\Support\IpFingerprint;

use App\Support\LeadProtectionStatus;

use App\Support\LeadStatus;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Mail;



class LeadProtectionService

{

    public function __construct(

        private FormProtectionService $protection,

        private LeadScoringService $scoring,

        private AttributionService $attribution,

        private LeadAcknowledgementService $acknowledgements,

    ) {}



    /**

     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|null

     */

    public function guard(Request $request, string $formKey, bool $requireIntent = true)

    {

        $check = $this->protection->validateSubmission($request, $formKey, $requireIntent);



        if ($check['reject']) {

            if ($check['rate_limited']) {

                return response(config('form_protection.messages.rate_limited'), 429);

            }



            return back()

                ->withInput($request->except(['password', 'password_confirmation', 'cf-turnstile-response']))

                ->withErrors(['form' => $check['message']]);

        }



        return null;

    }



    /**

     * @param  array<string, mixed>  $leadData

     * @return array{lead: Lead, notify: bool, success_message: string}

     */

    public function finalizeLead(Request $request, string $formKey, array $leadData): array

    {

        $durationMs = $this->protection->validateSubmission($request, $formKey, false)['duration_ms'];



        $type = (string) ($leadData['type'] ?? 'inquiry');

        $intent = $request->input('enquiry_intent') ?: ($leadData['enquiry_intent'] ?? null);

        $enquiryType = EnquiryType::fromLeadType($type, $intent);



        $scoring = $this->scoring->score($request, [

            ...$leadData,

            'enquiry_intent' => $intent,

            'submission_duration_ms' => $durationMs,

            'has_attachment' => filled($leadData['metadata']['drawing_path'] ?? null)

                || filled($leadData['metadata']['reference_upload'] ?? null),

        ]);



        $workflowStatus = $this->resolveWorkflowStatus($leadData, $scoring, $enquiryType);

        $attribution = $this->attribution->forLeadCreation($request);



        $lead = Lead::create([

            ...$leadData,

            ...$attribution,

            'enquiry_intent' => $intent,

            'enquiry_type' => $enquiryType,

            'status' => $workflowStatus,

            'protection_status' => $scoring['status'],

            'risk_score' => max(0, min(100, $scoring['score'])),

            'risk_reasons' => $scoring['reasons'],

            'lead_score' => $scoring['score'],

            'lead_score_reasons' => $scoring['reasons'],

            'priority' => $scoring['priority'],

            'ip_fingerprint' => IpFingerprint::hash($request->ip()),

            'duplicate_of_id' => $scoring['duplicate_of_id'],

            'notifications_suppressed' => $scoring['suppress_notification'],

            'submission_duration_ms' => $durationMs,

        ]);



        if ($scoring['duplicate_of_id']) {

            $this->handleDuplicateMerge($lead, $scoring['duplicate_of_id']);

        }



        LeadActivity::create([

            'lead_id' => $lead->id,

            'activity_type' => 'created',

            'body' => 'Lead submitted via ' . $formKey,

            'metadata' => [

                'form_key' => $formKey,

                'lead_score' => $scoring['score'],

                'protection_status' => $scoring['status'],

            ],

        ]);



        $this->protection->hitRateLimiters($request, $formKey);



        $notify = ! $scoring['suppress_notification']

            && LeadProtectionStatus::notifyAdmin($scoring['status'])

            && EnquiryType::isSalesQueue($enquiryType);



        return [

            'lead' => $lead,

            'notify' => $notify,

            'success_message' => $this->acknowledgements->message(

                $scoring['status'],

                $enquiryType,

                (bool) $scoring['duplicate_of_id']

            ),

        ];

    }



    public function notifyAdmin(Lead $lead, string $details, string $subject): bool

    {

        if ($lead->notifications_suppressed) {

            return false;

        }



        $recipient = $this->notificationRecipient($lead);

        if (! $recipient) {

            return false;

        }



        try {

            $scoreLine = "Score: {$lead->lead_score} (" . \App\Support\LeadPriority::scoreBandLabel((int) $lead->lead_score) . ") · {$lead->protectionStatusLabel()}";

            if (is_array($lead->lead_score_reasons) && $lead->lead_score_reasons !== []) {

                $scoreLine .= ' — ' . implode(', ', $lead->lead_score_reasons);

            }



            Mail::raw(

                $scoreLine . "\n\n" . $details,

                fn ($message) => $message->to($recipient)->subject($subject)

            );



            return true;

        } catch (\Throwable) {

            return false;

        }

    }



    private function notificationRecipient(Lead $lead): ?string

    {

        if ($lead->protection_status === LeadProtectionStatus::MARKETING_VENDOR

            || $lead->enquiry_type === EnquiryType::VENDOR_MARKETING) {

            return config('services.marketing_email') ?: config('services.admin_email');

        }



        return config('services.admin_email');

    }



    public function recipientFor(Lead $lead): ?string

    {

        return $this->notificationRecipient($lead);

    }



    /**

     * @param  array<string, mixed>  $scoring

     */

    private function resolveWorkflowStatus(array $leadData, array $scoring, string $enquiryType): string

    {

        if ($scoring['status'] === LeadProtectionStatus::DUPLICATE) {

            return LeadStatus::DUPLICATE;

        }



        if ($scoring['status'] === LeadProtectionStatus::MARKETING_VENDOR) {

            return LeadStatus::MARKETING_VENDOR;

        }



        if ($scoring['status'] === LeadProtectionStatus::SPAM_SUSPECTED) {

            return LeadStatus::SPAM_SUSPECTED;

        }



        if ($scoring['status'] === LeadProtectionStatus::BLOCKED) {

            return LeadStatus::BLOCKED;

        }



        if (! empty($leadData['whatsapp_verified'])) {

            return LeadStatus::VERIFIED;

        }



        if (in_array($enquiryType, [EnquiryType::CATALOGUE, EnquiryType::DEALER, EnquiryType::PROFESSIONAL_B2B], true)

            && ! $this->whatsappConfigured()) {

            return LeadStatus::UNVERIFIED;

        }



        return LeadStatus::normalize($leadData['status'] ?? LeadStatus::NEW);

    }



    private function whatsappConfigured(): bool

    {

        return filled(config('whatsapp.access_token')) && filled(config('whatsapp.phone_number_id'));

    }



    private function handleDuplicateMerge(Lead $duplicate, int $originalId): void

    {

        $original = Lead::find($originalId);

        if (! $original) {

            return;

        }



        $mergedMetadata = array_merge(

            is_array($original->metadata) ? $original->metadata : [],

            is_array($duplicate->metadata) ? $duplicate->metadata : []

        );



        $original->update([

            'duplicate_count' => (int) $original->duplicate_count + 1,

            'metadata' => $mergedMetadata,

        ]);



        LeadActivity::create([

            'lead_id' => $original->id,

            'activity_type' => 'merge',

            'body' => 'Duplicate submission merged from lead #' . $duplicate->id,

            'metadata' => ['duplicate_lead_id' => $duplicate->id],

        ]);

    }

}


