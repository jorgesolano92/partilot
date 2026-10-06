<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_entity_manager_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_entity_manager_invitations', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('confirmation_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_entity_manager_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('pending_entity_manager_invitations', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
        });
    }
};
