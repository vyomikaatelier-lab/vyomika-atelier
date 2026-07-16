<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_country_code', 5)->default('+91')->after('email');
            $table->string('mobile', 20)->nullable()->unique()->after('mobile_country_code');
            $table->string('whatsapp', 20)->nullable()->after('mobile');
            $table->string('city', 100)->nullable()->after('whatsapp');
            $table->string('account_type', 32)->default('customer')->after('city');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->default('Home');
            $table->string('name');
            $table->string('phone', 20);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('pincode', 12);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('whatsapp_otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_e164', 20)->index();
            $table->string('purpose', 20);
            $table->string('otp_hash');
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('send_count')->default(1);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['mobile_e164', 'purpose', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_otp_verifications');
        Schema::dropIfExists('customer_addresses');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mobile_country_code',
                'mobile',
                'whatsapp',
                'city',
                'account_type',
                'phone_verified_at',
            ]);
        });
    }
};
