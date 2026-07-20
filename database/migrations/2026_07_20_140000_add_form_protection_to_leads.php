<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('enquiry_intent', 50)->nullable()->after('type');
            $table->string('protection_status', 30)->default('needs_verification')->after('status');
            $table->unsignedSmallInteger('risk_score')->default(50)->after('protection_status');
            $table->json('risk_reasons')->nullable()->after('risk_score');
            $table->string('ip_fingerprint', 64)->nullable()->index()->after('risk_reasons');
            $table->foreignId('duplicate_of_id')->nullable()->after('ip_fingerprint')->constrained('leads')->nullOnDelete();
            $table->boolean('notifications_suppressed')->default(false)->after('duplicate_of_id');
            $table->unsignedInteger('submission_duration_ms')->nullable()->after('notifications_suppressed');
            $table->timestamp('restored_at')->nullable()->after('submission_duration_ms');
            $table->timestamp('false_positive_at')->nullable()->after('restored_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropColumn([
                'enquiry_intent',
                'protection_status',
                'risk_score',
                'risk_reasons',
                'ip_fingerprint',
                'notifications_suppressed',
                'submission_duration_ms',
                'restored_at',
                'false_positive_at',
            ]);
        });
    }
};
