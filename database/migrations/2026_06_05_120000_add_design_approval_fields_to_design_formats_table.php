<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->string('designer_type', 20)->nullable()->after('snapshot_path');
            $table->string('approval_status', 30)->nullable()->after('designer_type');
            $table->timestamp('submitted_for_approval_at')->nullable()->after('approval_status');
            $table->timestamp('approval_decided_at')->nullable()->after('submitted_for_approval_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approval_decided_at')->constrained('users')->nullOnDelete();
            $table->text('approval_rejection_reason')->nullable()->after('approved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'designer_type',
                'approval_status',
                'submitted_for_approval_at',
                'approval_decided_at',
                'approved_by_user_id',
                'approval_rejection_reason',
            ]);
        });
    }
};
