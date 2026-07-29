<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->timestamp('participation_export_locked_at')->nullable()->after('approval_rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->dropColumn('participation_export_locked_at');
        });
    }
};
