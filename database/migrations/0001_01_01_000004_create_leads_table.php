<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->enum('type', ['custom_order', 'contact', 'inquiry'])->default('inquiry');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('budget')->nullable();
            $table->string('preferred_contact')->nullable();
            $table->enum('status', ['new', 'contacted', 'quoted', 'converted', 'closed'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
