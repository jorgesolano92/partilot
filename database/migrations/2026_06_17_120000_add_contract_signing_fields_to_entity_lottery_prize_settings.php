<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entity_lottery_prize_settings')) {
            return;
        }

        Schema::table('entity_lottery_prize_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_token')) {
                $table->string('contract_token', 80)->nullable()->after('contract_signed_at');
            }
            if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_sent_at')) {
                $table->timestamp('contract_sent_at')->nullable()->after('contract_token');
            }
            if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_signed_by_user_id')) {
                $table->unsignedBigInteger('contract_signed_by_user_id')->nullable()->after('contract_sent_at');
                $table->foreign('contract_signed_by_user_id', 'elps_contract_signed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_signer_name')) {
                $table->string('contract_signer_name')->nullable()->after('contract_signed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('entity_lottery_prize_settings')) {
            return;
        }

        Schema::table('entity_lottery_prize_settings', function (Blueprint $table) {
            if (Schema::hasColumn('entity_lottery_prize_settings', 'contract_signed_by_user_id')) {
                $table->dropForeign('elps_contract_signed_by_fk');
                $table->dropColumn('contract_signed_by_user_id');
            }
            foreach (['contract_signer_name', 'contract_sent_at', 'contract_token'] as $col) {
                if (Schema::hasColumn('entity_lottery_prize_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
