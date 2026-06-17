<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partilot_billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('nif_cif', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->decimal('fee_per_participation_1000', 8, 4)->default(0.05);
            $table->decimal('fee_per_participation_5000', 8, 4)->default(0.04);
            $table->decimal('fee_per_participation_10000', 8, 4)->default(0.03);
            $table->decimal('fee_administration_per_participation', 8, 4)->default(0.03);
            $table->decimal('payment_management_commission', 8, 4)->default(0.03);
            $table->string('bank_account', 80)->nullable();
            $table->timestamps();
        });

        DB::table('partilot_billing_settings')->insert([
            'company_name' => 'El Búho Lotero',
            'nif_cif' => '16600600A',
            'address' => 'Avd. Club Deportivo 28',
            'postal_code' => '26007',
            'province' => 'La Rioja',
            'city' => 'Logroño',
            'phone' => '941 900 900',
            'email' => 'administracion@ejemplo.es',
            'fee_per_participation_1000' => 0.05,
            'fee_per_participation_5000' => 0.04,
            'fee_per_participation_10000' => 0.03,
            'fee_administration_per_participation' => 0.03,
            'payment_management_commission' => 0.03,
            'bank_account' => '1234 - 1234 - 1234 - 12 - 1234567890',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('partilot_billing_settings');
    }
};
