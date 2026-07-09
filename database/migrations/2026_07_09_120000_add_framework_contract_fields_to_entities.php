<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('contract_status', 32)->default('pending')->after('stripe_customer_id');
            $table->string('contract_reference', 32)->nullable()->after('contract_status');
            $table->string('contract_version', 32)->default('marco_v5')->after('contract_reference');
            $table->string('contract_token', 80)->nullable()->after('contract_version');
            $table->timestamp('contract_sent_at')->nullable()->after('contract_token');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_sent_at');
            $table->unsignedBigInteger('contract_signed_by_user_id')->nullable()->after('contract_signed_at');
            $table->string('contract_signer_name', 255)->nullable()->after('contract_signed_by_user_id');
            $table->string('contract_signer_nif', 32)->nullable()->after('contract_signer_name');
            $table->string('contract_pdf_path', 500)->nullable()->after('contract_signer_nif');
        });

        if (Schema::hasTable('entities')) {
            DB::table('entities')->update([
                'contract_status' => 'signed',
                'contract_signed_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn([
                'contract_status',
                'contract_reference',
                'contract_version',
                'contract_token',
                'contract_sent_at',
                'contract_signed_at',
                'contract_signed_by_user_id',
                'contract_signer_name',
                'contract_signer_nif',
                'contract_pdf_path',
            ]);
        });
    }
};
