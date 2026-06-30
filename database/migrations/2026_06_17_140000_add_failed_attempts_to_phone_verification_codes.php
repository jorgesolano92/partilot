<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_attempts')->default(0)->after('code_hash');
            $table->timestamp('locked_until')->nullable()->after('failed_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->dropColumn(['failed_attempts', 'locked_until']);
        });
    }
};
