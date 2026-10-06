<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_direct_debit_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administration_id')->constrained('administrations')->cascadeOnDelete();
            $table->string('message_id', 35)->unique();
            $table->string('payment_info_id', 35);
            $table->dateTime('creation_date');
            $table->date('collection_date');
            $table->unsignedInteger('number_of_transactions')->default(0);
            $table->decimal('control_sum', 12, 2)->default(0);
            $table->string('creditor_name');
            $table->string('creditor_nif_cif', 20)->nullable();
            $table->string('creditor_iban', 34);
            $table->string('creditor_scheme_id', 35)->nullable();
            $table->string('debtor_name');
            $table->string('debtor_nif_cif', 20)->nullable();
            $table->string('debtor_iban', 34);
            $table->string('debtor_mandate_id', 35);
            $table->date('debtor_mandate_signed_at');
            $table->string('sequence_type', 4)->default('RCUR');
            $table->string('xml_filename')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->index(['administration_id', 'status']);
        });

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->foreignId('billing_direct_debit_order_id')->nullable()->after('collected_at')->constrained('billing_direct_debit_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropForeign(['billing_direct_debit_order_id']);
            $table->dropColumn('billing_direct_debit_order_id');
        });

        Schema::dropIfExists('billing_direct_debit_orders');
    }
};
