<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entity_lottery_prize_settings')) {
            Schema::create('entity_lottery_prize_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('lottery_id');
                $table->string('prize_payment_mode', 20)->nullable();
                $table->timestamp('mode_locked_at')->nullable();
                $table->unsignedBigInteger('mode_locked_by_user_id')->nullable();
                $table->boolean('has_sold_digital_participations')->default(false);
                $table->decimal('funds_required_amount', 12, 2)->default(0);
                $table->decimal('funds_deposited_amount', 12, 2)->default(0);
                $table->string('funds_status', 20)->default('not_required');
                $table->timestamp('funds_confirmed_at')->nullable();
                $table->unsignedBigInteger('funds_confirmed_by_user_id')->nullable();
                $table->string('contract_status', 20)->default('not_required');
                $table->timestamp('contract_signed_at')->nullable();
                $table->boolean('online_payments_enabled')->default(false);
                $table->boolean('presencial_payments_enabled')->default(false);
                $table->text('blocked_user_message')->nullable();
                $table->text('unlocked_user_message')->nullable();
                $table->text('presencial_contact_text')->nullable();
                $table->string('presencial_contact_address')->nullable();
                $table->string('presencial_contact_city')->nullable();
                $table->string('presencial_contact_province')->nullable();
                $table->string('presencial_contact_schedule')->nullable();
                $table->string('presencial_contact_phone')->nullable();
                $table->string('presencial_contact_email')->nullable();
                $table->text('presencial_contact_notes')->nullable();
                $table->timestamps();

                $table->unique(['entity_id', 'lottery_id'], 'elps_entity_lottery_unique');

                $table->foreign('entity_id', 'elps_entity_fk')
                    ->references('id')->on('entities')->cascadeOnDelete();
                $table->foreign('lottery_id', 'elps_lottery_fk')
                    ->references('id')->on('lotteries')->cascadeOnDelete();
                $table->foreign('mode_locked_by_user_id', 'elps_mode_locked_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('funds_confirmed_by_user_id', 'elps_funds_confirmed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('entity_lottery_prize_activation_logs')) {
            Schema::create('entity_lottery_prize_activation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entity_lottery_prize_setting_id');
                $table->string('event', 80);
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('entity_lottery_prize_setting_id', 'elpal_setting_fk')
                    ->references('id')->on('entity_lottery_prize_settings')->cascadeOnDelete();
                $table->foreign('user_id', 'elpal_user_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_lottery_prize_activation_logs');
        Schema::dropIfExists('entity_lottery_prize_settings');
    }
};
