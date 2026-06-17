<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administration_id')->nullable()->constrained('administrations')->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('set_id')->nullable()->constrained('sets')->nullOnDelete();
            $table->string('payer_type', 20);
            $table->string('concept', 30);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->index(['administration_id', 'status']);
            $table->index(['set_id', 'concept', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->foreignId('management_fee_billing_charge_id')->nullable()->after('management_fee_payment_provider')->constrained('billing_charges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sets', function (Blueprint $table) {
            $table->dropForeign(['management_fee_billing_charge_id']);
            $table->dropColumn('management_fee_billing_charge_id');
        });

        Schema::dropIfExists('billing_charges');
    }
};
