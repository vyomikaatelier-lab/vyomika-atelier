<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_identities', function (Blueprint $table) {
            $table->id();
            $table->string('identity_type', 20);
            $table->string('value_hash', 64);
            $table->string('value_hint', 120)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();

            $table->index(['identity_type', 'value_hash', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_identities');
    }
};
