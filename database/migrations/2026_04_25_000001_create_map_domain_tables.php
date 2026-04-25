<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->unsignedInteger('district_no')->nullable()->index();
            $table->string('block_code', 10)->nullable();
            $table->string('display_name', 50);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('map_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('svg_path', 500);
            $table->string('view_box', 100)->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('map_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_version_id')->constrained('map_versions')->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('svg_element_id', 100);
            $table->string('area_type', 30)->default('district')->index();
            $table->string('display_name', 100)->nullable();
            $table->string('default_fill_color', 20)->default('#ffffff');
            $table->string('highlight_fill_color', 20)->default('#a8d0ff');
            $table->boolean('is_clickable')->default(true);
            $table->boolean('has_source_range')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['map_version_id', 'svg_element_id']);
            $table->index('district_id');
        });

        Schema::create('map_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_version_id')->constrained('map_versions')->cascadeOnDelete();
            $table->string('key_name', 50);
            $table->string('svg_group_id', 100);
            $table->string('display_name', 100);
            $table->boolean('is_default_visible')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['map_version_id', 'key_name']);
        });

        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('address_lot', 50);
            $table->string('building_name', 100)->nullable();
            $table->string('room_no', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('district_id');
            $table->index('address_lot');
            $table->unique(['district_id', 'address_lot', 'building_name', 'room_no'], 'households_location_unique');
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->unsignedInteger('member_no')->nullable()->index();
            $table->string('name', 100);
            $table->string('name_kana', 100)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->text('note')->nullable();
            $table->string('publication_status', 30)->default('public')->index();
            $table->string('membership_status', 30)->default('active')->index();
            $table->unsignedInteger('source_row_no')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('district_id');
            $table->index('household_id');
            $table->index('name');
        });

        Schema::create('sponsor_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->unsignedInteger('sponsor_no')->nullable()->index();
            $table->string('address_lot', 50)->nullable();
            $table->string('company_name', 150);
            $table->string('contact_name', 100)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('business_description', 255)->nullable();
            $table->text('note')->nullable();
            $table->string('membership_status', 30)->default('active')->index();
            $table->unsignedInteger('source_row_no')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('district_id');
            $table->index('company_name');
        });

        Schema::create('map_area_member_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_area_id')->constrained('map_areas')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->cascadeOnDelete();
            $table->foreignId('sponsor_member_id')->nullable()->constrained('sponsor_members')->cascadeOnDelete();
            $table->string('link_type', 30)->default('primary')->index();
            $table->timestamps();

            $table->index('map_area_id');
            $table->index('member_id');
            $table->index('sponsor_member_id');
            $table->unique(['map_area_id', 'member_id', 'sponsor_member_id'], 'area_member_link_unique');
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('import_type', 30)->index();
            $table->string('original_filename', 255);
            $table->string('stored_path', 500)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_no');
            $table->json('raw_payload');
            $table->string('status', 30)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->string('target_table', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->index('import_job_id');
            $table->index(['target_table', 'target_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50)->index();
            $table->string('target_table', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index('user_id');
            $table->index(['target_table', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('map_area_member_links');
        Schema::dropIfExists('sponsor_members');
        Schema::dropIfExists('members');
        Schema::dropIfExists('households');
        Schema::dropIfExists('map_layers');
        Schema::dropIfExists('map_areas');
        Schema::dropIfExists('map_versions');
        Schema::dropIfExists('districts');
    }
};
