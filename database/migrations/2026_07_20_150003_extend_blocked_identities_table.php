<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('blocked_identities', function (Blueprint $table) {
      $table->string('email_domain', 120)->nullable()->after('value_hint');
      $table->string('message_pattern', 255)->nullable()->after('email_domain');
      $table->timestamp('expires_at')->nullable()->after('is_active')->index();
    });
  }

  public function down(): void
  {
    Schema::table('blocked_identities', function (Blueprint $table) {
      $table->dropColumn(['email_domain', 'message_pattern', 'expires_at']);
    });
  }
};
