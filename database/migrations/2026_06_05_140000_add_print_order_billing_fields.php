<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_orders', function (Blueprint $table) {
            $table->foreignId('billing_charge_id')->nullable()->after('paid_at')->constrained('billing_charges')->nullOnDelete();
        });

        Schema::table('administrations', function (Blueprint $table) {
            $table->string('billing_sepa_mandate_id', 35)->nullable()->after('billing_remittance_frequency');
            $table->date('billing_sepa_mandate_signed_at')->nullable()->after('billing_sepa_mandate_id');
        });

        Schema::table('partilot_billing_settings', function (Blueprint $table) {
            $table->string('sepa_creditor_id', 35)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('partilot_billing_settings', function (Blueprint $table) {
            $table->dropColumn('sepa_creditor_id');
        });

        Schema::table('administrations', function (Blueprint $table) {
            $table->dropColumn(['billing_sepa_mandate_id', 'billing_sepa_mandate_signed_at']);
        });

        Schema::table('print_orders', function (Blueprint $table) {
            $table->dropForeign(['billing_charge_id']);
            $table->dropColumn('billing_charge_id');
        });
    }
};
