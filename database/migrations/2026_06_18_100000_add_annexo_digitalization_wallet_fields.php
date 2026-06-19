<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entity_lottery_prize_settings') && ! Schema::hasColumn('entity_lottery_prize_settings', 'online_payer')) {
            Schema::table('entity_lottery_prize_settings', function (Blueprint $table) {
                $table->string('online_payer', 20)->nullable()->after('prize_payment_mode');
            });
        }

        if (Schema::hasTable('participations') && ! Schema::hasColumn('participations', 'wallet_mode')) {
            Schema::table('participations', function (Blueprint $table) {
                $table->string('wallet_mode', 20)->nullable()->after('buyer_name');
            });
        }

        if (Schema::hasTable('lotteries') && ! Schema::hasColumn('lotteries', 'digitalization_closed_at')) {
            Schema::table('lotteries', function (Blueprint $table) {
                $table->timestamp('digitalization_closed_at')->nullable()->after('deadline_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('entity_lottery_prize_settings') && Schema::hasColumn('entity_lottery_prize_settings', 'online_payer')) {
            Schema::table('entity_lottery_prize_settings', function (Blueprint $table) {
                $table->dropColumn('online_payer');
            });
        }

        if (Schema::hasTable('participations') && Schema::hasColumn('participations', 'wallet_mode')) {
            Schema::table('participations', function (Blueprint $table) {
                $table->dropColumn('wallet_mode');
            });
        }

        if (Schema::hasTable('lotteries') && Schema::hasColumn('lotteries', 'digitalization_closed_at')) {
            Schema::table('lotteries', function (Blueprint $table) {
                $table->dropColumn('digitalization_closed_at');
            });
        }
    }
};
