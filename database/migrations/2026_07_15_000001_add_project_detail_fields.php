<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('category')->nullable()->after('location');
            $table->string('client')->nullable()->after('category');
            $table->text('design_details')->nullable()->after('content');
            $table->json('materials')->nullable()->after('design_details');
            $table->text('testimonial_quote')->nullable()->after('materials');
            $table->string('testimonial_author')->nullable()->after('testimonial_quote');
            $table->string('testimonial_role')->nullable()->after('testimonial_author');
            $table->string('meta_title')->nullable()->after('is_active');
            $table->text('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'client',
                'design_details',
                'materials',
                'testimonial_quote',
                'testimonial_author',
                'testimonial_role',
                'meta_title',
                'meta_description',
            ]);
        });
    }
};
