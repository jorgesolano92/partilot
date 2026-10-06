<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_entity_manager_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_entity_manager_invitations', 'confirmation_token')) {
                $table->string('confirmation_token', 64)->nullable()->unique()->after('permission_payments');
            }
            if (! Schema::hasColumn('pending_entity_manager_invitations', 'confirmation_sent_at')) {
                $table->timestamp('confirmation_sent_at')->nullable()->after('confirmation_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_entity_manager_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('pending_entity_manager_invitations', 'confirmation_sent_at')) {
                $table->dropColumn('confirmation_sent_at');
            }
            if (Schema::hasColumn('pending_entity_manager_invitations', 'confirmation_token')) {
                $table->dropColumn('confirmation_token');
            }
        });
    }
};
