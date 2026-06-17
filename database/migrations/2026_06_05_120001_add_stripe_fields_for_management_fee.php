<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partilot_billing_settings', function (Blueprint $table) {
            $table->string('stripe_publishable_key')->nullable()->after('bank_account');
            $table->text('stripe_secret_key')->nullable()->after('stripe_publishable_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret_key');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->string('management_fee_stripe_payment_intent_id')->nullable()->after('management_fee_paid_by_user_id');
            $table->string('management_fee_payment_provider', 20)->nullable()->after('management_fee_stripe_payment_intent_id');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('entity_pays_print_fee');
        });

        Schema::table('administrations', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('prepago_integration_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('administrations', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->dropColumn(['management_fee_stripe_payment_intent_id', 'management_fee_payment_provider']);
        });

        Schema::table('partilot_billing_settings', function (Blueprint $table) {
            $table->dropColumn(['stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret']);
        });
    }
};
