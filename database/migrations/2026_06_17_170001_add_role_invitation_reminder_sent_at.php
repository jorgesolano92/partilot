<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            $table->timestamp('role_invitation_reminder_sent_at')->nullable()->after('confirmation_sent_at');
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->timestamp('role_invitation_reminder_sent_at')->nullable()->after('confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            $table->dropColumn('role_invitation_reminder_sent_at');
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('role_invitation_reminder_sent_at');
        });
    }
};
