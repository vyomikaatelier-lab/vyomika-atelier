<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable receipt for a signature-valid payment webhook.
 * The payload and signature are not stored. payload_sha256 is unique so a
 * repeated delivery cannot insert a second outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('event', 80);
            $table->char('payload_sha256', 64)->unique();
            $table->string('razorpay_order_id')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('outcome', 80);
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_receipts');
    }
};
