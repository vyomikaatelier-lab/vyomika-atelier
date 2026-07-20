<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('leads', function (Blueprint $table) {
      $table->string('enquiry_type', 40)->nullable()->after('type')->index();
      $table->smallInteger('lead_score')->default(0)->after('risk_reasons');
      $table->json('lead_score_reasons')->nullable()->after('lead_score');
      $table->string('priority', 20)->nullable()->after('lead_score_reasons')->index();
      $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
      $table->timestamp('next_follow_up_at')->nullable()->after('assigned_to')->index();
      $table->timestamp('last_contacted_at')->nullable()->after('next_follow_up_at');
      $table->decimal('expected_order_value', 12, 2)->nullable()->after('last_contacted_at');
      $table->string('lost_reason', 255)->nullable()->after('expected_order_value');
      $table->unsignedSmallInteger('duplicate_count')->default(0)->after('duplicate_of_id');
      $table->boolean('whatsapp_verified')->default(false)->after('duplicate_count');
      $table->string('utm_source', 120)->nullable()->after('whatsapp_verified');
      $table->string('utm_medium', 120)->nullable()->after('utm_source');
      $table->string('utm_campaign', 120)->nullable()->after('utm_medium');
      $table->string('utm_term', 120)->nullable()->after('utm_campaign');
      $table->string('utm_content', 120)->nullable()->after('utm_term');
      $table->string('referrer', 500)->nullable()->after('utm_content');
      $table->string('landing_page', 500)->nullable()->after('referrer');
      $table->string('first_touch_source', 120)->nullable()->after('landing_page');
      $table->string('last_touch_source', 120)->nullable()->after('first_touch_source');
      $table->string('device_type', 40)->nullable()->after('last_touch_source');
      $table->json('internal_notes')->nullable()->after('admin_notes');
    });

    Schema::table('leads', function (Blueprint $table) {
      $table->index('status');
      $table->index('lead_score');
      $table->index('email');
      $table->index('phone');
      $table->index('created_at');
      $table->index('first_touch_source');
    });

        $typeMap = config('lead_qualification.type_to_enquiry', []);
        $statusMap = config('lead_qualification.legacy_status_map', []);

        DB::table('leads')->orderBy('id')->chunkById(200, function ($leads) use ($typeMap, $statusMap) {
            foreach ($leads as $lead) {
                $enquiryType = $typeMap[$lead->type] ?? 'general';
                $leadScore = (int) ($lead->risk_score ?? 0);
                $priority = match (true) {
                    $leadScore >= 70 => 'hot',
                    $leadScore >= 40 => 'high',
                    $leadScore >= 20 => 'medium',
                    default => 'low',
                };
                $status = $statusMap[$lead->status] ?? $lead->status;

                DB::table('leads')->where('id', $lead->id)->update([
                    'enquiry_type' => $enquiryType,
                    'lead_score' => $leadScore,
                    'lead_score_reasons' => $lead->risk_reasons,
                    'priority' => $priority,
                    'status' => $status,
                ]);
            }
        });
  }

  public function down(): void
  {
    Schema::table('leads', function (Blueprint $table) {
      $table->dropForeign(['assigned_to']);
      $table->dropColumn([
        'enquiry_type',
        'lead_score',
        'lead_score_reasons',
        'priority',
        'assigned_to',
        'next_follow_up_at',
        'last_contacted_at',
        'expected_order_value',
        'lost_reason',
        'duplicate_count',
        'whatsapp_verified',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'landing_page',
        'first_touch_source',
        'last_touch_source',
        'device_type',
        'internal_notes',
      ]);
    });
  }
};
