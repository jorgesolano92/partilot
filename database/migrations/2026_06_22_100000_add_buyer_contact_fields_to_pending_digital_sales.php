<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_digital_sales', function (Blueprint $table) {
            $table->string('buyer_phone', 20)->nullable()->after('email');
            $table->string('notify_channel', 20)->nullable()->after('buyer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('pending_digital_sales', function (Blueprint $table) {
            $table->dropColumn(['buyer_phone', 'notify_channel']);
        });
    }
};
