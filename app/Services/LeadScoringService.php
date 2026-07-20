<?php

namespace App\Services;

use App\Models\BlockedIdentity;
use App\Models\Lead;
use App\Support\DisposableEmailChecker;
use App\Support\EnquiryType;
use App\Support\IpFingerprint;
use App\Support\LeadPriority;
use App\Support\LeadProtectionStatus;
use App\Support\SpamContentAnalyzer;
use Illuminate\Http\Request;

class LeadScoringService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{score: int, reasons: list<string>, status: string, duplicate_of_id: int|null, suppress_notification: bool, priority: string}
     */
    public function score(Request $request, array $context): array
    {
        $score = 0;
        $reasons = [];
        $email = strtolower(trim((string) ($context['email'] ?? '')));
        $phone = preg_replace('/\D/', '', (string) ($context['phone'] ?? ''));
        $message = (string) ($context['message'] ?? '');
        $intent = (string) ($context['enquiry_intent'] ?? $request->input('enquiry_intent', ''));
        $ipHash = IpFingerprint::hash($request->ip());
        $positive = config('lead_qualification.scoring.positive', []);
        $negative = config('lead_qualification.scoring.negative', []);

        if ($this->isBlocked('email', $email) || $this->isEmailDomainBlocked($email)) {
            return $this->outcome(0, ['blocked_email'], LeadProtectionStatus::BLOCKED);
        }

        if ($phone && $this->isBlocked('phone', $phone)) {
            return $this->outcome(0, ['blocked_phone'], LeadProtectionStatus::BLOCKED);
        }

        if ($ipHash && $this->isBlocked('ip', $ipHash)) {
            return $this->outcome(0, ['blocked_ip'], LeadProtectionStatus::BLOCKED);
        }

        if ($this->messageMatchesBlockedPattern($message)) {
            return $this->outcome(0, ['blocked_message_pattern'], LeadProtectionStatus::BLOCKED);
        }

        if ($intent === config('form_protection.vendor_intent')) {
            return $this->outcome(40, ['vendor_intent'], LeadProtectionStatus::MARKETING_VENDOR);
        }

        if (! empty($context['whatsapp_verified'])) {
            $score += (int) ($positive['whatsapp_verified'] ?? 20);
            $reasons[] = 'whatsapp_verified';
        }

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $score += (int) ($positive['valid_email'] ?? 5);
            $reasons[] = 'valid_email';
        }

        if ($email && DisposableEmailChecker::isDisposable($email)) {
            $score -= (int) ($negative['disposable'] ?? 30);
            $reasons[] = 'disposable_email';
        }

        if (filled($context['project_location'] ?? null)) {
            $score += (int) ($positive['project_location'] ?? 10);
            $reasons[] = 'project_location';
        }

        if (filled($context['service_slug'] ?? null) || filled($context['design_slug'] ?? null) || filled($context['product_interest'] ?? null)) {
            $score += (int) ($positive['product_selected'] ?? 10);
            $reasons[] = 'product_selected';
        }

        if (filled($context['dimensions'] ?? null) || filled($context['approximate_size'] ?? null)) {
            $score += (int) ($positive['measurements'] ?? 15);
            $reasons[] = 'measurements';
        }

        if (filled($context['budget'] ?? null) || filled($context['budget_range'] ?? null)) {
            $score += (int) ($positive['budget'] ?? 10);
            $reasons[] = 'budget';
        }

        if (filled($context['timeline'] ?? null) || filled($context['required_timeline'] ?? null)) {
            $score += (int) ($positive['timeline'] ?? 10);
            $reasons[] = 'timeline';
        }

        if (! empty($context['has_attachment'])) {
            $score += (int) ($positive['reference_upload'] ?? 15);
            $reasons[] = 'reference_upload';
        }

        if ($this->hasMeaningfulDescription($message)) {
            $score += (int) ($positive['meaningful_description'] ?? 10);
            $reasons[] = 'meaningful_description';
        }

        if (! empty($context['architect_details']) || in_array($context['type'] ?? '', ['professional_application'], true)) {
            $score += (int) ($positive['architect_details'] ?? 10);
            $reasons[] = 'architect_details';
        }

        if ($intent === 'active_project') {
            $score += (int) ($positive['active_project'] ?? 15);
            $reasons[] = 'active_project';
        }

        $durationMs = $context['submission_duration_ms'] ?? null;
        if ($durationMs !== null && $durationMs < (int) config('form_protection.min_submission_seconds', 3) * 1000) {
            $score -= (int) ($negative['too_fast'] ?? 30);
            $reasons[] = 'very_fast_submission';
        }

        $spam = SpamContentAnalyzer::analyze($message);
        if ($spam['url_count'] > (int) config('form_protection.max_urls_in_message', 3)) {
            $score -= (int) ($negative['multiple_urls'] ?? 25);
            $reasons[] = 'multiple_urls';
        }

        if ($spam['spam_phrases'] !== []) {
            $score -= (int) ($negative['marketing_pitch'] ?? 35);
            $reasons[] = 'marketing_pitch';
        }

        if ($this->hasRecentIpSubmissions($ipHash)) {
            $score -= (int) ($negative['repeated_ip'] ?? 20);
            $reasons[] = 'repeated_ip_submissions';
        }

        $duplicate = $this->findDuplicate($email, $phone, $ipHash, $message);
        if ($duplicate) {
            return [
                'score' => $score - (int) ($negative['duplicate'] ?? 20),
                'reasons' => array_values(array_unique([...$reasons, 'duplicate_submission'])),
                'status' => LeadProtectionStatus::DUPLICATE,
                'duplicate_of_id' => $duplicate->id,
                'suppress_notification' => true,
                'priority' => LeadPriority::fromScore($score),
            ];
        }

        $status = match (true) {
            $score >= (config('lead_qualification.score_bands.hot') ?? 70) => LeadProtectionStatus::VERIFIED,
            $score < 0 || SpamContentAnalyzer::isSuspicious($message) => LeadProtectionStatus::SPAM_SUSPECTED,
            default => LeadProtectionStatus::NEEDS_VERIFICATION,
        };

        return [
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'status' => $status,
            'duplicate_of_id' => null,
            'suppress_notification' => false,
            'priority' => LeadPriority::fromScore($score),
        ];
    }

    /**
     * Recalculate score for an existing lead (admin update).
     *
     * @return array{score: int, reasons: list<string>, priority: string}
     */
    public function scoreExistingLead(Lead $lead): array
    {
        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $request = request();

        $result = $this->score($request ?: new \Illuminate\Http\Request, [
            'email' => $lead->email,
            'phone' => $lead->phone,
            'message' => $lead->message,
            'enquiry_intent' => $lead->enquiry_intent,
            'project_location' => $metadata['project_location'] ?? null,
            'service_slug' => $lead->service_slug,
            'design_slug' => $lead->design_slug,
            'dimensions' => $lead->dimensions,
            'budget' => $lead->budget,
            'timeline' => $metadata['timeline'] ?? null,
            'has_attachment' => $lead->hasAttachment(),
            'whatsapp_verified' => $lead->whatsapp_verified,
            'type' => $lead->type,
            'submission_duration_ms' => $lead->submission_duration_ms,
        ]);

        if ($lead->duplicate_of_id) {
            $result['score'] -= (int) config('lead_qualification.scoring.negative.duplicate', 20);
            $result['reasons'][] = 'duplicate_submission';
        }

        $result['priority'] = LeadPriority::fromScore($result['score']);

        return [
            'score' => $result['score'],
            'reasons' => array_values(array_unique($result['reasons'])),
            'priority' => $result['priority'],
        ];
    }

    /**
     * @param  list<string>  $reasons
     * @return array{score: int, reasons: list<string>, status: string, duplicate_of_id: int|null, suppress_notification: bool, priority: string}
     */
    private function outcome(int $score, array $reasons, string $status): array
    {
        return [
            'score' => $score,
            'reasons' => $reasons,
            'status' => $status,
            'duplicate_of_id' => null,
            'suppress_notification' => in_array($status, [LeadProtectionStatus::DUPLICATE, LeadProtectionStatus::BLOCKED], true),
            'priority' => LeadPriority::fromScore($score),
        ];
    }

    private function isBlocked(string $type, string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $hash = $type === 'ip'
            ? $value
            : hash('sha256', strtolower($value));

        return BlockedIdentity::query()
            ->active()
            ->where('identity_type', $type)
            ->where('value_hash', $hash)
            ->exists();
    }

    private function isEmailDomainBlocked(string $email): bool
    {
        if (! str_contains($email, '@')) {
            return false;
        }

        $domain = strtolower(substr($email, strrpos($email, '@') + 1));

        return BlockedIdentity::query()
            ->active()
            ->where('identity_type', 'email_domain')
            ->where(function ($query) use ($domain) {
                $query->where('email_domain', $domain)
                    ->orWhere('value_hash', hash('sha256', $domain));
            })
            ->exists();
    }

    private function messageMatchesBlockedPattern(string $message): bool
    {
        $patterns = BlockedIdentity::query()
            ->active()
            ->where('identity_type', 'message_pattern')
            ->whereNotNull('message_pattern')
            ->pluck('message_pattern');

        foreach ($patterns as $pattern) {
            if ($pattern && @preg_match('/' . $pattern . '/i', $message)) {
                return true;
            }
        }

        return false;
    }

    private function findDuplicate(string $email, string $phone, ?string $ipHash, string $message): ?Lead
    {
        $lookback = now()->subHours((int) config('lead_qualification.duplicate_lookback_hours', 24));
        $fingerprint = app(FormProtectionService::class)->messageFingerprint($message);

        $query = Lead::query()
            ->where('created_at', '>=', $lookback)
            ->whereNull('duplicate_of_id')
            ->whereNotIn('protection_status', [LeadProtectionStatus::BLOCKED])
            ->latest();

        if ($email) {
            $byEmail = (clone $query)->where('email', $email)->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        if ($phone) {
            $byPhone = (clone $query)->where('phone', 'like', '%' . substr($phone, -10))->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        if ($ipHash) {
            $byIp = (clone $query)->where('ip_fingerprint', $ipHash)->first();
            if ($byIp) {
                return $byIp;
            }
        }

        if (strlen(trim($message)) >= 20) {
            $service = app(FormProtectionService::class);
            $byMessage = Lead::query()
                ->where('created_at', '>=', $lookback)
                ->whereNull('duplicate_of_id')
                ->get()
                ->first(function (Lead $lead) use ($fingerprint, $service) {
                    return $service->messageFingerprint((string) $lead->message) === $fingerprint;
                });

            if ($byMessage) {
                return $byMessage;
            }
        }

        return null;
    }

    private function hasRecentIpSubmissions(?string $ipHash): bool
    {
        if (! $ipHash) {
            return false;
        }

        return Lead::query()
            ->where('ip_fingerprint', $ipHash)
            ->where('created_at', '>=', now()->subHours(24))
            ->count() >= 2;
    }

    private function hasMeaningfulDescription(string $message): bool
    {
        $trimmed = trim($message);

        return strlen($trimmed) >= 40 && str_word_count($trimmed) >= 8;
    }
}
