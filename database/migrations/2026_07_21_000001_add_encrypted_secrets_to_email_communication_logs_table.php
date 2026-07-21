<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_communication_logs', function (Blueprint $table) {
            $table->text('encrypted_secrets')->nullable()->after('mail_payload');
        });
    }

    public function down(): void
    {
        Schema::table('email_communication_logs', function (Blueprint $table) {
            $table->dropColumn('encrypted_secrets');
        });
    }
};
