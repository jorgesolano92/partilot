<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administrations', function (Blueprint $table) {
            $table->string('billing_payment_mode', 20)->default('card')->after('stripe_customer_id');
            $table->string('billing_remittance_frequency', 20)->nullable()->after('billing_payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('administrations', function (Blueprint $table) {
            $table->dropColumn(['billing_payment_mode', 'billing_remittance_frequency']);
        });
    }
};
