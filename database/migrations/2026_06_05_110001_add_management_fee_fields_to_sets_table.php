<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sets', function (Blueprint $table) {
            $table->string('management_fee_status', 20)->nullable()->after('status');
            $table->decimal('management_fee_amount', 10, 2)->nullable()->after('management_fee_status');
            $table->decimal('management_fee_unit_price', 8, 4)->nullable()->after('management_fee_amount');
            $table->unsignedInteger('management_fee_participation_count')->nullable()->after('management_fee_unit_price');
            $table->string('management_fee_payer', 20)->nullable()->after('management_fee_participation_count');
            $table->timestamp('management_fee_paid_at')->nullable()->after('management_fee_payer');
            $table->foreignId('management_fee_paid_by_user_id')->nullable()->after('management_fee_paid_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sets', function (Blueprint $table) {
            $table->dropForeign(['management_fee_paid_by_user_id']);
            $table->dropColumn([
                'management_fee_status',
                'management_fee_amount',
                'management_fee_unit_price',
                'management_fee_participation_count',
                'management_fee_payer',
                'management_fee_paid_at',
                'management_fee_paid_by_user_id',
            ]);
        });
    }
};
