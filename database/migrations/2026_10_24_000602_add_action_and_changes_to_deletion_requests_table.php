<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deletion_requests', function (Blueprint $table) {
            $table->string('action', 20)->default('delete')->after('entity_type');
            $table->json('changes')->nullable()->after('details');
            $table->unsignedBigInteger('entity_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('deletion_requests')->whereNull('entity_id')->exists()) {
            throw new RuntimeException(
                'Resolve pending creation requests before rolling back this migration.',
            );
        }

        Schema::table('deletion_requests', function (Blueprint $table) {
            $table->dropColumn(['action', 'changes']);
            $table->unsignedBigInteger('entity_id')->nullable(false)->change();
        });
    }
};
