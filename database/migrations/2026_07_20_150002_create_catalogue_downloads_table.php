<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('catalogue_downloads', function (Blueprint $table) {
      $table->id();
      $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
      $table->string('email');
      $table->string('phone', 20)->nullable();
      $table->string('profession', 120)->nullable();
      $table->string('city', 120)->nullable();
      $table->string('download_token', 64)->unique();
      $table->timestamp('expires_at')->nullable();
      $table->timestamp('downloaded_at')->nullable();
      $table->string('ip_fingerprint', 64)->nullable();
      $table->timestamps();

      $table->index('email');
      $table->index('expires_at');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('catalogue_downloads');
  }
};
