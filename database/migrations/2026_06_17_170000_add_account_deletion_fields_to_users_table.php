<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('status');
            $table->timestamp('deletion_scheduled_at')->nullable()->after('deletion_requested_at');
            $table->string('deletion_status', 40)->nullable()->after('deletion_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deletion_requested_at', 'deletion_scheduled_at', 'deletion_status']);
        });
    }
};
