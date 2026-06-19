<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Administration;
use App\Models\Manager;
use App\Models\User;
use App\Models\Entity;
use Illuminate\Support\Facades\Hash;
use Exception;
use App\Support\ParticipationTicketReference;

class ApiController extends Controller
{
    private const DEFAULT_PANEL_PASSWORD = '12345678';

    public function test()
    {
        // Schema::table('participation_collection_items', function (Blueprint $table) {
        //     if (! Schema::hasColumn('participation_collection_items', 'entity_id')) {
        //         $table->foreignId('entity_id')->nullable()->after('participation_id')
        //             ->constrained('entities')->nullOnDelete();
        //     }
        //     if (! Schema::hasColumn('participation_collection_items', 'amount')) {
        //         $table->decimal('amount', 12, 2)->nullable()->after('entity_id');
        //     }
        // });
        
        // if (! Schema::hasTable('entity_lottery_prize_settings')) {
        //     Schema::create('entity_lottery_prize_settings', function (Blueprint $table) {
        //         $table->id();
        //         $table->unsignedBigInteger('entity_id');
        //         $table->unsignedBigInteger('lottery_id');
        //         $table->string('prize_payment_mode', 20)->nullable();
        //         $table->timestamp('mode_locked_at')->nullable();
        //         $table->unsignedBigInteger('mode_locked_by_user_id')->nullable();
        //         $table->boolean('has_sold_digital_participations')->default(false);
        //         $table->decimal('funds_required_amount', 12, 2)->default(0);
        //         $table->decimal('funds_deposited_amount', 12, 2)->default(0);
        //         $table->string('funds_status', 20)->default('not_required');
        //         $table->timestamp('funds_confirmed_at')->nullable();
        //         $table->unsignedBigInteger('funds_confirmed_by_user_id')->nullable();
        //         $table->string('contract_status', 20)->default('not_required');
        //         $table->timestamp('contract_signed_at')->nullable();
        //         $table->boolean('online_payments_enabled')->default(false);
        //         $table->boolean('presencial_payments_enabled')->default(false);
        //         $table->text('blocked_user_message')->nullable();
        //         $table->text('unlocked_user_message')->nullable();
        //         $table->text('presencial_contact_text')->nullable();
        //         $table->string('presencial_contact_address')->nullable();
        //         $table->string('presencial_contact_city')->nullable();
        //         $table->string('presencial_contact_province')->nullable();
        //         $table->string('presencial_contact_schedule')->nullable();
        //         $table->string('presencial_contact_phone')->nullable();
        //         $table->string('presencial_contact_email')->nullable();
        //         $table->text('presencial_contact_notes')->nullable();
        //         $table->timestamps();

        //         $table->unique(['entity_id', 'lottery_id'], 'elps_entity_lottery_unique');

        //         $table->foreign('entity_id', 'elps_entity_fk')
        //             ->references('id')->on('entities')->cascadeOnDelete();
        //         $table->foreign('lottery_id', 'elps_lottery_fk')
        //             ->references('id')->on('lotteries')->cascadeOnDelete();
        //         $table->foreign('mode_locked_by_user_id', 'elps_mode_locked_by_fk')
        //             ->references('id')->on('users')->nullOnDelete();
        //         $table->foreign('funds_confirmed_by_user_id', 'elps_funds_confirmed_by_fk')
        //             ->references('id')->on('users')->nullOnDelete();
        //     });
        // }

        // if (! Schema::hasTable('entity_lottery_prize_activation_logs')) {
        //     Schema::create('entity_lottery_prize_activation_logs', function (Blueprint $table) {
        //         $table->id();
        //         $table->unsignedBigInteger('entity_lottery_prize_setting_id');
        //         $table->string('event', 80);
        //         $table->json('payload')->nullable();
        //         $table->unsignedBigInteger('user_id')->nullable();
        //         $table->timestamp('created_at')->useCurrent();

        //         $table->foreign('entity_lottery_prize_setting_id', 'elpal_setting_fk')
        //             ->references('id')->on('entity_lottery_prize_settings')->cascadeOnDelete();
        //         $table->foreign('user_id', 'elpal_user_fk')
        //             ->references('id')->on('users')->nullOnDelete();
        //     });
        // }

        // Schema::table('entity_lottery_prize_settings', function (Blueprint $table) {
        //     if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_token')) {
        //         $table->string('contract_token', 80)->nullable()->after('contract_signed_at');
        //     }
        //     if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_sent_at')) {
        //         $table->timestamp('contract_sent_at')->nullable()->after('contract_token');
        //     }
        //     if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_signed_by_user_id')) {
        //         $table->unsignedBigInteger('contract_signed_by_user_id')->nullable()->after('contract_sent_at');
        //         $table->foreign('contract_signed_by_user_id', 'elps_contract_signed_by_fk')
        //             ->references('id')->on('users')->nullOnDelete();
        //     }
        //     if (! Schema::hasColumn('entity_lottery_prize_settings', 'contract_signer_name')) {
        //         $table->string('contract_signer_name')->nullable()->after('contract_signed_by_user_id');
        //     }
        // });

        // if (Schema::hasTable('entity_lottery_prize_settings') && ! Schema::hasColumn('entity_lottery_prize_settings', 'online_payer')) {
        //     Schema::table('entity_lottery_prize_settings', function (Blueprint $table) {
        //         $table->string('online_payer', 20)->nullable()->after('prize_payment_mode');
        //     });
        // }

        // if (Schema::hasTable('participations') && ! Schema::hasColumn('participations', 'wallet_mode')) {
        //     Schema::table('participations', function (Blueprint $table) {
        //         $table->string('wallet_mode', 20)->nullable()->after('buyer_name');
        //     });
        // }

        // if (Schema::hasTable('lotteries') && ! Schema::hasColumn('lotteries', 'digitalization_closed_at')) {
        //     Schema::table('lotteries', function (Blueprint $table) {
        //         $table->timestamp('digitalization_closed_at')->nullable()->after('deadline_date');
        //     });
        // }

        // Schema::create('billing_direct_debit_orders', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('administration_id')->constrained('administrations')->cascadeOnDelete();
        //     $table->string('message_id', 35)->unique();
        //     $table->string('payment_info_id', 35);
        //     $table->dateTime('creation_date');
        //     $table->date('collection_date');
        //     $table->unsignedInteger('number_of_transactions')->default(0);
        //     $table->decimal('control_sum', 12, 2)->default(0);
        //     $table->string('creditor_name');
        //     $table->string('creditor_nif_cif', 20)->nullable();
        //     $table->string('creditor_iban', 34);
        //     $table->string('creditor_scheme_id', 35)->nullable();
        //     $table->string('debtor_name');
        //     $table->string('debtor_nif_cif', 20)->nullable();
        //     $table->string('debtor_iban', 34);
        //     $table->string('debtor_mandate_id', 35);
        //     $table->date('debtor_mandate_signed_at');
        //     $table->string('sequence_type', 4)->default('RCUR');
        //     $table->string('xml_filename')->nullable();
        //     $table->string('status', 20)->default('draft');
        //     $table->text('notes')->nullable();
        //     $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        //     $table->timestamp('exported_at')->nullable();
        //     $table->timestamp('collected_at')->nullable();
        //     $table->timestamps();

        //     $table->index(['administration_id', 'status']);
        // });

        // Schema::create('billing_charges', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('administration_id')->nullable()->constrained('administrations')->nullOnDelete();
        //     $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
        //     $table->foreignId('set_id')->nullable()->constrained('sets')->nullOnDelete();
        //     $table->string('payer_type', 20);
        //     $table->string('concept', 30);
        //     $table->string('source_type', 30);
        //     $table->unsignedBigInteger('source_id');
        //     $table->decimal('amount', 10, 2);
        //     $table->string('currency', 3)->default('EUR');
        //     $table->string('description')->nullable();
        //     $table->string('status', 20)->default('pending');
        //     $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        //     $table->timestamp('collected_at')->nullable();
        //     $table->timestamps();

        //     $table->index(['administration_id', 'status']);
        //     $table->index(['set_id', 'concept', 'status']);
        //     $table->index(['source_type', 'source_id']);
        // });

        // Schema::table('billing_charges', function (Blueprint $table) {
        //     $table->foreignId('billing_direct_debit_order_id')->nullable()->after('collected_at')->constrained('billing_direct_debit_orders')->nullOnDelete();
        // });
        
        // Schema::table('print_orders', function (Blueprint $table) {
        //     $table->foreignId('billing_charge_id')->nullable()->after('paid_at')->constrained('billing_charges')->nullOnDelete();
        // });

        Schema::table('administrations', function (Blueprint $table) {
            $table->string('billing_payment_mode', 20)->default('card')->after('stripe_customer_id');
            $table->string('billing_remittance_frequency', 20)->nullable()->after('billing_payment_mode');
        });

        Schema::table('administrations', function (Blueprint $table) {
            $table->string('billing_sepa_mandate_id', 35)->nullable()->after('billing_remittance_frequency');
            $table->date('billing_sepa_mandate_signed_at')->nullable()->after('billing_sepa_mandate_id');
        });

        Schema::table('partilot_billing_settings', function (Blueprint $table) {
            $table->string('sepa_creditor_id', 35)->nullable()->after('bank_account');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->foreignId('management_fee_billing_charge_id')->nullable()->after('management_fee_payment_provider')->constrained('billing_charges')->nullOnDelete();
        });

        Schema::table('design_formats', function (Blueprint $table) {
            $table->string('designer_type', 20)->nullable()->after('snapshot_path');
            $table->string('approval_status', 30)->nullable()->after('designer_type');
            $table->timestamp('submitted_for_approval_at')->nullable()->after('approval_status');
            $table->timestamp('approval_decided_at')->nullable()->after('submitted_for_approval_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approval_decided_at')->constrained('users')->nullOnDelete();
            $table->text('approval_rejection_reason')->nullable()->after('approved_by_user_id');
        });

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

        Schema::table('sets', function (Blueprint $table) {
            $table->string('management_fee_status', 20)->nullable()->after('status');
            $table->decimal('management_fee_amount', 10, 2)->nullable()->after('management_fee_status');
            $table->decimal('management_fee_unit_price', 8, 4)->nullable()->after('management_fee_amount');
            $table->unsignedInteger('management_fee_participation_count')->nullable()->after('management_fee_unit_price');
            $table->string('management_fee_payer', 20)->nullable()->after('management_fee_participation_count');
            $table->timestamp('management_fee_paid_at')->nullable()->after('management_fee_payer');
            $table->foreignId('management_fee_paid_by_user_id')->nullable()->after('management_fee_paid_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('lottery_deadline_admin_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lottery_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 32);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'lottery_id'], 'lottery_deadline_admin_decision_unique');
        });
        
        Schema::create('lottery_deadline_closure_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lottery_id')->constrained()->cascadeOnDelete();
            $table->date('effective_deadline');
            $table->foreignId('devolution_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('participations_sold')->default(0);
            $table->unsignedInteger('participations_returned_digital')->default(0);
            $table->decimal('total_liquidation', 12, 2)->default(0);
            $table->string('status', 32);
            $table->text('message')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['entity_id', 'lottery_id']);
            $table->index('status');
        });
        
        Schema::create('lottery_deadline_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lottery_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('days_before');
            $table->string('channel', 32);
            $table->string('recipient', 191);
            $table->date('reminded_on');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(
                ['entity_id', 'lottery_id', 'days_before', 'channel', 'recipient', 'reminded_on'],
                'lottery_deadline_reminder_unique'
            );
        });
        return "ok";
        Schema::table('print_configurations', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(1)->after('id');
        });

        Schema::table('print_orders', function (Blueprint $table) {
            $table->foreignId('print_configuration_id')
                ->nullable()
                ->after('id')
                ->constrained('print_configurations')
                ->nullOnDelete();
        });

        if (Schema::hasTable('design_external_invitations') && ! Schema::hasColumn('design_external_invitations', 'print_configuration_id')) {
            Schema::table('design_external_invitations', function (Blueprint $table) {
                $table->foreignId('print_configuration_id')
                    ->nullable()
                    ->after('set_id')
                    ->constrained('print_configurations')
                    ->nullOnDelete();
            });
        }

        $defaultId = DB::table('print_configurations')->orderBy('id')->value('id');
        if ($defaultId) {
            DB::table('print_orders')
                ->whereNull('print_configuration_id')
                ->update(['print_configuration_id' => $defaultId]);

            if (Schema::hasColumn('design_external_invitations', 'print_configuration_id')) {
                DB::table('design_external_invitations')
                    ->whereNull('print_configuration_id')
                    ->update(['print_configuration_id' => $defaultId]);
            }
        }

        return "ok";
        
        Schema::table('participation_collections', function (Blueprint $table) {
            $table->string('status', 30)->default('pending_verification')->after('importe_total');
            $table->string('confirmation_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('confirmation_sent_at')->nullable()->after('confirmation_token');
            $table->timestamp('verified_at')->nullable()->after('confirmation_sent_at');
            $table->timestamp('expires_at')->nullable()->after('verified_at');
        });

        Schema::table('participation_collections', function (Blueprint $table) {
            $table->timestamp('collected_at')->nullable()->change();
        });

        // Solicitudes existentes (ya procesadas) → verificadas
        DB::table('participation_collections')
            ->whereNotNull('collected_at')
            ->update([
                'status' => 'verified',
                'verified_at' => DB::raw('collected_at'),
            ]);
            
        Schema::table('sepa_payment_beneficiaries', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('remittance_info');
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        // Sincronizar beneficiarios de órdenes ya marcadas como listo (datos previos al cambio)
        DB::table('sepa_payment_beneficiaries')
            ->join('sepa_payment_orders', 'sepa_payment_beneficiaries.sepa_payment_order_id', '=', 'sepa_payment_orders.id')
            ->where('sepa_payment_orders.status', 'listo')
            ->update([
                'sepa_payment_beneficiaries.status' => 'paid',
                'sepa_payment_beneficiaries.paid_at' => DB::raw('COALESCE(sepa_payment_beneficiaries.paid_at, sepa_payment_orders.updated_at)'),
            ]);

        return "ok";
        
        Schema::table('administrations', function (Blueprint $table) {
            $table->string('prepago_integration_name')->nullable()->after('account');
            $table->string('prepago_api_url', 500)->nullable()->after('prepago_integration_name');
            $table->string('prepago_auth_method', 32)->default('apikey')->after('prepago_api_url');
            $table->string('prepago_api_prefix', 32)->nullable()->after('prepago_auth_method');
            $table->text('prepago_api_key')->nullable()->after('prepago_api_prefix');
            $table->boolean('prepago_use_partilot_default')->default(false)->after('prepago_api_key');
            $table->boolean('prepago_integration_enabled')->default(true)->after('prepago_use_partilot_default');
        });
        
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'billing_iban')) {
                $table->string('billing_iban', 34)->nullable()->after('email');
            }
        });
        return "ok";
        DB::statement('ALTER TABLE pending_digital_sales MODIFY valid_until DATETIME NOT NULL');

        Schema::table('pending_digital_sales', function (Blueprint $table) {
            $table->unsignedTinyInteger('buyer_sms_sent_count')->default(0)->after('link_code');
        });

        return "ok";
        Schema::table('pending_digital_sales', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
        Schema::table('pending_digital_sales', function (Blueprint $table) {
            $table->string('link_code', 12)->nullable()->unique()->after('registration_token');
        });
        return "ok";
        Schema::create('pending_digital_sales', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lottery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('set_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->string('payment_method', 30)->nullable();
            $table->string('registration_token', 64)->unique();
            $table->string('link_code', 12)->nullable()->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('valid_until')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::dropIfExists('pending_digital_sale_participations');

        Schema::create('pending_digital_sale_participations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pending_digital_sale_id');
            $table->unsignedBigInteger('participation_id');
            $table->foreign('pending_digital_sale_id', 'pds_pdsp_sale_fk')
                ->references('id')->on('pending_digital_sales')->cascadeOnDelete();
            $table->foreign('participation_id', 'pds_pdsp_part_fk')
                ->references('id')->on('participations')->cascadeOnDelete();
            $table->unique(['participation_id']);
        });

        Schema::create('phone_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE participations MODIFY COLUMN status ENUM(
            'disponible',
            'reservada',
            'vendida',
            'devuelta',
            'anulada',
            'perdida',
            'asignada',
            'pagada',
            'reserva_venta_digital'
        ) DEFAULT 'disponible'");
        
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'recipient_user_id')) {
                $table->foreignId('recipient_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('notifications', 'kind')) {
                $table->string('kind', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('notifications', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
        
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            /** android | ios | web (PWA / messaging web) */
            $table->string('platform', 32)->default('android');
            $table->timestamps();

            $table->unique('token');
            $table->index(['user_id', 'platform']);
        });

        if (Schema::hasColumn('users', 'fcm_token')) {
            $rows = DB::table('users')->whereNotNull('fcm_token')->where('fcm_token', '!=', '')->get(['id', 'fcm_token']);
            foreach ($rows as $row) {
                DB::table('user_fcm_tokens')->insert([
                    'user_id' => $row->id,
                    'token' => $row->fcm_token,
                    'platform' => 'android',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }

        return "ok";
        
        if (! Schema::hasTable('design_external_invitations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE design_external_invitations MODIFY email VARCHAR(255) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE design_external_invitations ALTER COLUMN email DROP NOT NULL');

            return;
        }

        // sqlite u otros: intentar el cambio estándar
        Schema::table('design_external_invitations', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        return "ok";
        
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
        
        Schema::create('background_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 64);
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('requested_by_user_id')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->unsignedBigInteger('administration_id')->nullable()->index();
            $table->unsignedBigInteger('set_id')->nullable()->index();
            $table->string('resource_key', 120)->nullable()->index();
            $table->string('task_hash', 64)->nullable()->index();
            $table->json('payload')->nullable();
            $table->unsignedInteger('progress_total')->default(0);
            $table->unsignedInteger('progress_done')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('result_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['requested_by_user_id', 'created_at']);
            $table->index(['type', 'status']);
            $table->index(['resource_key', 'status']);
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        
        return "ok";

        Schema::table('managers', function (Blueprint $table) {
            if (! Schema::hasColumn('managers', 'pending_primary')) {
                $table->boolean('pending_primary')->default(false)->after('is_primary');
            }
        });
        
        DB::statement('ALTER TABLE print_orders MODIFY design_format_id BIGINT UNSIGNED NULL');
        
        Schema::table('print_orders', function (Blueprint $table) {
            $table->string('payment_provider', 30)->nullable()->after('status');
            $table->string('payment_intent_id', 120)->nullable()->unique()->after('payment_provider');
            $table->string('payment_status', 30)->default('pending')->after('payment_intent_id');
            $table->timestamp('paid_at')->nullable()->after('sent_at');
        });

        Schema::create('print_order_status_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('print_order_id')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->unsignedBigInteger('set_id')->nullable()->index();
            $table->unsignedBigInteger('design_format_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 80);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        
        Schema::create('print_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 30)->unique();
            $table->unsignedBigInteger('design_format_id')->index();
            $table->unsignedBigInteger('set_id')->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->unsignedBigInteger('lottery_id')->nullable()->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->string('status', 40)->default('pendiente_revision')->index();
            $table->string('print_size', 30)->nullable();
            $table->unsignedInteger('participations_per_book')->nullable();
            $table->string('back_mode', 10)->nullable();
            $table->decimal('quoted_amount', 10, 2)->default(0);
            $table->json('quote_breakdown')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::table('design_external_invitations', function (Blueprint $table) {
            $table->string('print_size', 30)->nullable()->after('comment');
            $table->unsignedInteger('participations_per_book')->nullable()->after('print_size');
            $table->string('back_mode', 10)->nullable()->after('participations_per_book');
            $table->decimal('quoted_amount', 10, 2)->nullable()->after('back_mode');
            $table->json('quote_breakdown')->nullable()->after('quoted_amount');
        });

        Schema::create('print_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('nif_cif', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->decimal('price_design', 10, 4)->default(0);
            $table->decimal('price_participation', 10, 4)->default(0);
            $table->decimal('price_back_bw', 10, 4)->default(0);
            $table->decimal('price_back_color', 10, 4)->default(0);
            $table->decimal('price_taco_25', 10, 4)->default(0);
            $table->decimal('price_taco_50', 10, 4)->default(0);
            $table->decimal('price_taco_100', 10, 4)->default(0);
            $table->string('bank_account', 80)->nullable();
            $table->timestamps();
        });

        Schema::table('print_configurations', function (Blueprint $table) {
            $table->string('stripe_publishable_key', 255)->nullable()->after('bank_account');
            $table->text('stripe_secret_key')->nullable()->after('stripe_publishable_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret_key');
        });

        Schema::create('design_lock_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('set_id')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->unsignedBigInteger('design_format_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 80);
            $table->string('message', 500)->nullable();
            $table->unsignedInteger('assigned_count')->default(0);
            $table->unsignedInteger('status_locked_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
        
        return 'ok';

        Schema::table('devolutions', function (Blueprint $table) {
            $table->json('special_prize_settlement')->nullable()->after('notes');
        });
        // \App\Models\User::factory()->create([
        //     'name' => 'Test Admin',
        //     'email' => 'admin@partilot.es',
        //     'password' => bcrypt(12345678),
        //     'role' => User::ROLE_SUPER_ADMIN,
        // ]);

        Schema::table('managers', function (Blueprint $table) {
            if (! Schema::hasColumn('managers', 'requires_password_setup')) {
                $table->boolean('requires_password_setup')
                    ->default(false)
                    ->after('confirmation_sent_at');
            }
        });
        
        Schema::create('pending_entity_manager_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('permission_sellers')->default(true);
            $table->boolean('permission_design')->default(true);
            $table->boolean('permission_statistics')->default(true);
            $table->boolean('permission_payments')->default(true);
            $table->timestamps();

            $table->unique(['entity_id', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('panel_login_username', 64)->nullable()->unique()->after('panel_account_id');
        });

        Schema::create('panel_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['token_hash', 'expires_at']);
        });

        $this->backfillPanelLoginUsernames();

        // Schema::table('managers', function (Blueprint $table) {
        //     $table->string('confirmation_token', 80)->nullable()->after('permission_payments');
        //     $table->timestamp('confirmation_sent_at')->nullable()->after('confirmation_token');
        // });
        return 'ok';
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();

            // Clave estable derivada del título del JSON (para poder referenciar la plantilla)
            $table->string('key')->unique();
            $table->string('title')->nullable();

            // Texto “crudo” con cuándo dispara / condiciones (para depurar y usar en fases futuras)
            $table->text('trigger_text')->nullable();
            $table->text('condition_text')->nullable();

            // Plantilla: asunto y cuerpo (para render futuro desde backend)
            $table->string('subject_template')->nullable();
            $table->longText('body_template')->nullable();

            // Flags importados del JSON (no se usan aún para automatizar disparos)
            $table->boolean('enabled_email')->default(true);
            $table->boolean('enabled_notification')->default(false);

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        
        Schema::create('email_communication_logs', function (Blueprint $table) {
            $table->id();

            // Referencia opcional a plantilla importada desde el JSON
            $table->string('template_key')->nullable();

            // Tipo interno del mensaje/actuación (mailable/tipo)
            $table->string('message_type')->nullable();

            // Quién envía (requerido por tu especificación)
            $table->string('sender_type'); // superadmin | administracion | entidad
            $table->unsignedBigInteger('sender_user_id')->nullable();

            // A quién se envía
            $table->string('recipient_email');
            $table->string('recipient_role')->nullable();
            $table->unsignedBigInteger('recipient_user_id')->nullable();

            // Para reenviar: guardamos clase y payload (id simple) para poder reconstruir el Mailable
            $table->string('mail_class')->nullable();
            $table->json('mail_payload')->nullable();

            // Estados
            $table->string('status', 20); // pending | sent | cancelled | resent
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('resent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();

            $table->text('error_message')->nullable();
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['recipient_email', 'status']);
            $table->index(['sender_type', 'status']);
        });
        return 'ok';
        Schema::table('users', function (Blueprint $table) {
            $table->string('panel_account_type', 32)->nullable()->after('role');
            $table->unsignedBigInteger('panel_account_id')->nullable()->after('panel_account_type');
            $table->index(['panel_account_type', 'panel_account_id'], 'users_panel_account_idx');
        });

        DB::table('users')->update([
            'panel_account_type' => null,
            'panel_account_id' => null,
        ]);

        foreach (Administration::query()->orderBy('id')->cursor() as $adm) {
            $this->ensureAdministrationPanelUser($adm);
        }

        foreach (Entity::query()->orderBy('id')->cursor() as $entity) {
            $this->ensureEntityPanelUser($entity);
        }

        return 'ok';

        DB::statement("ALTER TABLE devolution_details MODIFY COLUMN action ENUM('devolver', 'vender', 'devolver_vendedor', 'anular') NOT NULL");
        
        Schema::create('design_external_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('lottery_id');
            $table->unsignedBigInteger('set_id');
            $table->unsignedBigInteger('created_by_user_id');
            $table->text('comment')->nullable();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending'); // pending, sent, in_progress, completed
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('design_format_id')->nullable(); // cuando el invitado guarda el diseño
            $table->string('orden_id', 32)->nullable(); // ej. #EN9802 para listado
            $table->timestamps();
        });

        Schema::create('design_external_invitation_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('design_external_invitation_id');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->foreign('design_external_invitation_id', 'deif_invitation_fk')
                ->references('id')->on('design_external_invitations')->onDelete('cascade');
        });
        
        // DB::statement("ALTER TABLE participations MODIFY COLUMN status ENUM('disponible', 'reservada', 'vendida', 'devuelta', 'anulada', 'perdida', 'asignada', 'pagada') DEFAULT 'disponible'");

        // DB::statement("ALTER TABLE participation_activity_logs MODIFY COLUMN activity_type ENUM(
        //     'created', 'assigned', 'returned_by_seller', 'sold', 'returned_to_administration',
        //     'status_changed', 'cancelled', 'modified', 'paid'
        // ) NOT NULL");

        // DB::statement("ALTER TABLE devolution_details MODIFY COLUMN action ENUM('devolver', 'vender', 'devolver_vendedor') NOT NULL");

        // Schema::table('participations', function (Blueprint $table) {
        //     $table->string('payment_method', 50)->nullable()->after('sale_amount');
        // });
        
        // Schema::table('devolutions', function (Blueprint $table) {
        //     $table->decimal('total_liquidation', 12, 2)->nullable()->after('total_participations');
        // });
        
        Schema::table('sepa_payment_beneficiaries', function (Blueprint $table) {
            $table->foreignId('participation_collection_id')->nullable()->after('remittance_info')
                ->constrained('participation_collections')->nullOnDelete();
        });
        
        Schema::table('participation_collections', function (Blueprint $table) {
            $table->foreignId('sepa_payment_order_id')->nullable()->after('collected_at')->constrained('sepa_payment_orders')->nullOnDelete();
        });
        
        Schema::table('participations', function (Blueprint $table) {
            $table->timestamp('donated_at')->nullable()->after('collected_at');
        });
        
        // Schema::create('participation_gifts', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('participation_id')->constrained()->onDelete('cascade');
        //     $table->foreignId('from_user_id')->constrained('users')->onDelete('cascade');
        //     $table->foreignId('to_user_id')->constrained('users')->onDelete('cascade');
        //     $table->timestamps();

        //     $table->unique('participation_id'); // Una participación solo se puede regalar una vez
        //     $table->index(['from_user_id', 'to_user_id']);
        // });

        // Schema::table('sellers', function (Blueprint $table) {
        //     $table->string('confirmation_token', 64)->nullable()->unique()->after('status');
        //     $table->timestamp('confirmation_sent_at')->nullable()->after('confirmation_token');
        // });

        // Schema::table('participations', function (Blueprint $table) {
        //     $table->string('payment_method', 50)->nullable()->after('sale_amount');
        // });
        
        // Schema::create('participation_donations', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->constrained()->onDelete('cascade');
        //     $table->string('nombre')->nullable();
        //     $table->string('apellidos')->nullable();
        //     $table->string('nif', 20)->nullable();
        //     $table->decimal('importe_donacion', 10, 2)->default(0);
        //     $table->decimal('importe_codigo', 10, 2)->default(0);
        //     $table->string('codigo_recarga', 20)->nullable();
        //     $table->boolean('anonima')->default(false);
        //     $table->timestamp('donated_at');
        //     $table->timestamps();
            
        //     $table->index(['user_id', 'donated_at']);
        //     $table->index('codigo_recarga');
        // });

        // Schema::create('participation_donation_items', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('donation_id')->constrained('participation_donations')->onDelete('cascade');
        //     $table->foreignId('participation_id')->constrained()->onDelete('cascade');
        //     $table->timestamps();
            
        //     $table->unique(['donation_id', 'participation_id'], 'part_don_items_unique');
        // });
        
        Schema::create('participation_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nombre');
            $table->string('apellidos');
            $table->string('nif', 20);
            $table->string('iban', 24);
            $table->decimal('importe_total', 10, 2);
            $table->timestamp('collected_at');
            $table->timestamps();
            
            $table->index(['user_id', 'collected_at']);
        });
        
        Schema::create('participation_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('participation_collections')->onDelete('cascade');
            $table->foreignId('participation_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['collection_id', 'participation_id'], 'part_col_items_unique');
        });
        
        Schema::table('participations', function (Blueprint $table) {
            $table->timestamp('collected_at')->nullable()->after('buyer_nif');
        });

        Schema::table('administrations', function (Blueprint $table) {
            $table->string('admin_number')->nullable()->after('receiving');
        });

        return;
        // Añadir campos lottery_type_code e is_special a la tabla lotteries
        // if (!Schema::hasColumn('lotteries', 'lottery_type_code')) {
        //     Schema::table('lotteries', function (Blueprint $table) {
        //         $table->string('lottery_type_code', 2)->nullable()->after('lottery_type_id')
        //               ->comment('Código del tipo de sorteo: J, X, S, N, B, V');
        //     });
        // }

        // if (!Schema::hasColumn('lotteries', 'is_special')) {
        //     Schema::table('lotteries', function (Blueprint $table) {
        //         $table->boolean('is_special')->default(false)->after('lottery_type_code')
        //               ->comment('Indica si es un sorteo especial (ej: 15€ Especial)');
        //     });
        // }

        // return response()->json([
        //     'message' => 'Migración completada exitosamente',
        //     'fields_added' => [
        //         'lottery_type_code' => 'string(2) nullable - Código del tipo de sorteo',
        //         'is_special' => 'boolean default(false) - Indica si es sorteo especial'
        //     ],
        //     'note' => 'lottery_types.identificador mantiene códigos simples (J,X,S,N,B,V) para compatibilidad'
        // ]);

        // Schema::table('scrutiny_entity_results', function (Blueprint $table) {
        //     $table->integer('total_non_winning')->default(0)->after('total_returned');
        // });

        // return;

        // Schema::dropIfExists('administration_lottery_scrutinies');
        // Schema::create('administration_lottery_scrutinies', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('administration_id')->onDelete('cascade');
        //     $table->foreignId('lottery_id')->onDelete('cascade');
        //     $table->foreignId('lottery_result_id')->nullable()->onDelete('cascade');
            
        //     // Metadatos del escrutinio
        //     $table->timestamp('scrutiny_date')->nullable();
        //     $table->boolean('is_scrutinized')->default(false);
        //     $table->json('scrutiny_summary')->nullable(); // Resumen: total premiadas, no premiadas, importe total
            
        //     // Información del usuario que realizó el escrutinio
        //     $table->foreignId('scrutinized_by')->nullable()->constrained('users')->onDelete('set null');
        //     $table->text('comments')->nullable();
            
        //     $table->timestamps();
            
        //     // Índices
        //     /*$table->index(['administration_id', 'lottery_id']);
        //     $table->index(['lottery_id', 'is_scrutinized']);
        //     $table->unique(['administration_id', 'lottery_id']); // Un escrutinio por administración por sorteo*/
        // });

        // Schema::dropIfExists('scrutiny_entity_results');
        // Schema::create('scrutiny_entity_results', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('administration_lottery_scrutiny_id')->onDelete('cascade');
        //     $table->foreignId('entity_id')->onDelete('cascade');
            
        //     // Datos de las reservas de la entidad
        //     $table->json('reserved_numbers')->nullable(); // Números reservados por la entidad
        //     $table->integer('total_reserved')->default(0); // Total de números reservados
        //     $table->integer('total_issued')->default(0); // Total emitidos
        //     $table->integer('total_sold')->default(0); // Total vendidos
        //     $table->integer('total_returned')->default(0); // Total devueltos
            
        //     // Resultados del escrutinio
        //     $table->json('winning_numbers')->nullable(); // Números ganadores de esta entidad
        //     $table->integer('total_winning')->default(0); // Total de números premiados
        //     $table->decimal('total_prize_amount', 15, 2)->default(0); // Importe total de premios
        //     $table->decimal('prize_per_participation', 10, 2)->default(0); // Premio por participación
            
        //     // Detalles de premios por categoría
        //     $table->json('prize_breakdown')->nullable(); // Desglose de premios: {tipo_premio: {numeros: [], importe: 0}}
            
        //     $table->timestamps();
            
        //     // Índices
        //     /*$table->index(['administration_lottery_scrutiny_id', 'entity_id']);
        //     $table->index(['entity_id', 'total_winning']);*/
        // });


        // return;
        // // Primero, actualizar la estructura de la tabla users
        // $this->updateUsersTable();
        
        // // Luego, eliminar las restricciones de clave foránea existentes si existen
        // $this->dropForeignKeys();
        
        // // Crear tablas temporales para guardar los datos existentes
        // Schema::create('sellers_temp', function (Blueprint $table) {
        //     $table->id();
        //     $table->integer('user_id')->nullable();
        //     $table->string("image")->nullable();
        //     $table->string("name")->nullable();
        //     $table->string("last_name")->nullable();
        //     $table->string("last_name2")->nullable();
        //     $table->string("nif_cif")->nullable();
        //     $table->string("birthday")->nullable();
        //     $table->string("email")->nullable();
        //     $table->string("phone")->nullable();
        //     $table->string("comment")->nullable();
        //     $table->integer("status")->nullable();
        //     $table->integer('entity_id')->nullable();
        //     $table->timestamps();
        // });

        // Schema::create('managers_temp', function (Blueprint $table) {
        //     $table->id();
        //     $table->integer('user_id')->nullable();
        //     $table->string("image")->nullable();
        //     $table->string("name")->nullable();
        //     $table->string("last_name")->nullable();
        //     $table->string("last_name2")->nullable();
        //     $table->string("nif_cif")->nullable();
        //     $table->string("birthday")->nullable();
        //     $table->string("email")->nullable();
        //     $table->string("phone")->nullable();
        //     $table->string("comment")->nullable();
        //     $table->timestamps();
        // });

        // // Copiar datos existentes a tablas temporales
        // if (Schema::hasTable('sellers')) {
        //     DB::statement('INSERT INTO sellers_temp (id, user_id, image, name, last_name, last_name2, nif_cif, birthday, email, phone, comment, status, entity_id, created_at, updated_at) 
        //                   SELECT id, user_id, image, name, last_name, last_name2, nif_cif, birthday, email, phone, comment, status, entity_id, created_at, updated_at FROM sellers');
        // }
        
        // if (Schema::hasTable('managers')) {
        //     DB::statement('INSERT INTO managers_temp (id, user_id, image, name, last_name, last_name2, nif_cif, birthday, email, phone, comment, created_at, updated_at) 
        //                   SELECT id, user_id, image, name, last_name, last_name2, nif_cif, birthday, email, phone, comment, created_at, updated_at FROM managers');
        // }

        // // Migrar datos de managers a users
        // $this->migrateManagersToUsers();
        
        // // Migrar datos de sellers a users
        // $this->migrateSellersToUsers();
        
        // // Eliminar las tablas existentes
        // Schema::dropIfExists('sellers');
        // Schema::dropIfExists('managers');
        
        // // Crear la nueva estructura de sellers
        // Schema::create('sellers', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->constrained()->onDelete('cascade');
        //     $table->foreignId('entity_id')->constrained()->onDelete('cascade');
        //     $table->timestamps();
        //     $table->unique(['user_id', 'entity_id']);
        // });

        // // Crear la nueva estructura de managers
        // Schema::create('managers', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
        //     $table->foreignId('entity_id')->nullable()->constrained()->onDelete('cascade');
        //     $table->foreignId('administration_id')->nullable()->constrained()->onDelete('cascade');
        //     $table->timestamps();
        //     $table->unique(['user_id', 'entity_id']);
        //     $table->unique(['user_id', 'administration_id']);
        // });

        // // Migrar las relaciones
        // $this->migrateRelations();
        
        // // Eliminar tablas temporales
        // Schema::dropIfExists('sellers_temp');
        // Schema::dropIfExists('managers_temp');

        // // Eliminar la columna manager_id de entities si existe
        // if (Schema::hasColumn('entities', 'manager_id')) {
        //     Schema::table('entities', function (Blueprint $table) {
        //         $table->dropColumn('manager_id');
        //     });
        // }

        // // Agregar la columna administration_id a managers si no existe
        // if (!Schema::hasColumn('managers', 'administration_id')) {
        //     Schema::table('managers', function (Blueprint $table) {
        //         $table->foreignId('administration_id')->nullable()->constrained()->onDelete('cascade');
        //     });
        // }

        // Agregar campos 'series' y 'billetes_serie' a la tabla lottery_types si no existen
        // $fieldsAdded = [];
        // if (!Schema::hasColumn('lottery_types', 'series')) {
        //     Schema::table('lottery_types', function (Blueprint $table) {
        //         $table->integer('series')->default(10)->after('ticket_price')->comment('Cantidad de series para el tipo de sorteo');
        //     });
        //     $fieldsAdded[] = 'series';
        // }
        // if (!Schema::hasColumn('lottery_types', 'billetes_serie')) {
        //     Schema::table('lottery_types', function (Blueprint $table) {
        //         $table->integer('billetes_serie')->default(100000)->after('series')->comment('Billetes por cada serie');
        //     });
        //     $fieldsAdded[] = 'billetes_serie';
        // }

        // if (!empty($fieldsAdded)) {
        //     return response()->json([
        //         'message' => 'Migración completada exitosamente',
        //         'fields_added' => $fieldsAdded
        //     ]);
        // }

        // return response()->json(['message' => 'No se realizaron cambios en la estructura de la base de datos']);

        Schema::table('sellers', function (Blueprint $table) {
            // Campos para vendedores externos (replicando users)
            $table->string('email')->nullable()->after('entity_id');
            $table->string('name')->nullable()->after('email');
            $table->string('last_name')->nullable()->after('name');
            $table->string('last_name2')->nullable()->after('last_name');
            $table->string('nif_cif')->nullable()->after('last_name2');
            $table->date('birthday')->nullable()->after('nif_cif');
            $table->boolean('status')->default(true)->after('birthday');
            $table->string('phone')->nullable()->after('status');
            $table->string('image')->nullable()->after('phone');
            
            // Campo para diferenciar tipo de vendedor
            $table->enum('seller_type', ['partilot', 'externo'])->default('partilot')->after('status');
            
            // Comentarios adicionales
            $table->text('comment')->nullable()->after('seller_type');
        });
    }

    private function backfillPanelLoginUsernames(): void
    {
        User::query()
            ->where('panel_account_type', 'administration')
            ->whereNotNull('panel_account_id')
            ->orderBy('id')
            ->each(function (User $user) {
                $adm = Administration::query()->find($user->panel_account_id);
                if (! $adm) {
                    return;
                }
                $base = Administration::panelLoginUsernameFromParts($adm->receiving, $adm->admin_number);
                $username = Administration::ensureUniquePanelLoginUsername($base, $user->id);
                DB::table('users')->where('id', $user->id)->update([
                    'panel_login_username' => $username,
                    'updated_at' => now(),
                ]);
            });
    }

    private function ensureAdministrationPanelUser(Administration $adm): void
    {
        $email = trim((string) $adm->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $displayName = \App\Models\Administration::panelDisplayNameFromParts($adm->name, $adm->society);

        $user = User::where('email', $email)->first();

        if ($user) {
            if ($user->isPanelAccount()) {
                if ($user->panel_account_type !== 'administration' || (int) $user->panel_account_id !== (int) $adm->id) {
                    return;
                }
            } else {
                $isGestorOfThisAdmin = Manager::query()
                    ->where('user_id', $user->id)
                    ->where('administration_id', $adm->id)
                    ->whereNull('entity_id')
                    ->exists();
                if (! $isGestorOfThisAdmin) {
                    return;
                }
            }

            $user->update([
                'name' => $displayName,
                'nif_cif' => $adm->nif_cif,
                'phone' => $adm->phone,
                'role' => User::ROLE_ADMINISTRATION,
                'panel_account_type' => 'administration',
                'panel_account_id' => $adm->id,
                'status' => true,
            ]);
        } else {
            $user = User::create([
                'name' => $displayName,
                'email' => $email,
                'password' => Hash::make(self::DEFAULT_PANEL_PASSWORD),
                'nif_cif' => $adm->nif_cif,
                'phone' => $adm->phone,
                'role' => User::ROLE_ADMINISTRATION,
                'panel_account_type' => 'administration',
                'panel_account_id' => $adm->id,
                'status' => true,
            ]);
        }

        Manager::query()
            ->where('administration_id', $adm->id)
            ->whereNull('entity_id')
            ->update(['is_primary' => false]);

        Manager::updateOrCreate(
            [
                'user_id' => $user->id,
                'administration_id' => $adm->id,
                'entity_id' => null,
            ],
            [
                'is_primary' => true,
                'permission_sellers' => true,
                'permission_design' => true,
                'permission_statistics' => true,
                'permission_payments' => true,
                'status' => 1,
            ]
        );
    }

    private function ensureEntityPanelUser(Entity $entity): void
    {
        $email = trim((string) $entity->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $displayName = trim((string) $entity->name) ?: 'Entidad';

        $user = User::where('email', $email)->first();

        if ($user) {
            if ($user->panel_account_type === 'administration') {
                return;
            }
            if ($user->isPanelAccount() && $user->panel_account_type === 'entity' && (int) $user->panel_account_id !== (int) $entity->id) {
                return;
            }
            if (! $user->isPanelAccount()) {
                $isGestorOfThisEntity = Manager::query()
                    ->where('user_id', $user->id)
                    ->where('entity_id', $entity->id)
                    ->exists();
                if (! $isGestorOfThisEntity) {
                    return;
                }
            }

            $user->update([
                'name' => $displayName,
                'nif_cif' => $entity->nif_cif,
                'phone' => $entity->phone,
                'role' => User::ROLE_ENTITY,
                'panel_account_type' => 'entity',
                'panel_account_id' => $entity->id,
                'status' => true,
            ]);
        } else {
            $user = User::create([
                'name' => $displayName,
                'email' => $email,
                'password' => Hash::make(self::DEFAULT_PANEL_PASSWORD),
                'nif_cif' => $entity->nif_cif,
                'phone' => $entity->phone,
                'role' => User::ROLE_ENTITY,
                'panel_account_type' => 'entity',
                'panel_account_id' => $entity->id,
                'status' => true,
            ]);
        }

        Manager::query()
            ->where('entity_id', $entity->id)
            ->update(['is_primary' => false]);

        Manager::updateOrCreate(
            [
                'user_id' => $user->id,
                'entity_id' => $entity->id,
            ],
            [
                'administration_id' => null,
                'is_primary' => true,
                'permission_sellers' => true,
                'permission_design' => true,
                'permission_statistics' => true,
                'permission_payments' => true,
                'status' => 1,
            ]
        );
    }

    /**
     * Actualizar la estructura de la tabla users
     */
    private function updateUsersTable()
    {
        // Verificar si las columnas ya existen para evitar errores
        $columns = [
            'last_name',
            'last_name2', 
            'nif_cif',
            'birthday',
            'phone',
            'comment',
            'image',
            'status'
        ];

        foreach ($columns as $column) {
            if (!Schema::hasColumn('users', $column)) {
                try {
                    Schema::table('users', function (Blueprint $table) use ($column) {
                        switch ($column) {
                            case 'last_name':
                            case 'last_name2':
                            case 'nif_cif':
                            case 'birthday':
                            case 'phone':
                            case 'image':
                                $table->string($column)->nullable();
                                break;
                            case 'comment':
                                $table->text($column)->nullable();
                                break;
                            case 'status':
                                $table->boolean($column)->default(true);
                                break;
                        }
                    });
                } catch (Exception $e) {
                    // La columna ya existe o hay otro error, continuar
                }
            }
        }
    }

    /**
     * Eliminar restricciones de clave foránea existentes
     */
    private function dropForeignKeys()
    {
        // Eliminar restricciones de sellers si existen
        if (Schema::hasTable('sellers')) {
            try {
                Schema::table('sellers', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (Exception $e) {
                // La restricción no existe, continuar
            }
            
            try {
                Schema::table('sellers', function (Blueprint $table) {
                    $table->dropForeign(['entity_id']);
                });
            } catch (Exception $e) {
                // La restricción no existe, continuar
            }
        }

        // Eliminar restricciones de managers si existen
        if (Schema::hasTable('managers')) {
            try {
                Schema::table('managers', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (Exception $e) {
                // La restricción no existe, continuar
            }
            
            try {
                Schema::table('managers', function (Blueprint $table) {
                    $table->dropForeign(['entity_id']);
                });
            } catch (Exception $e) {
                // La restricción no existe, continuar
            }
        }
    }

    /**
     * Migrar datos de managers a users
     */
    private function migrateManagersToUsers()
    {
        $managers = DB::table('managers_temp')->get();
        
        foreach ($managers as $manager) {
            if ($manager->email) {
                // Verificar si ya existe un usuario con ese email
                $existingUser = DB::table('users')->where('email', $manager->email)->first();
                
                if (!$existingUser) {
                    // Crear nuevo usuario con los datos del manager
                    $userId = DB::table('users')->insertGetId([
                        'name' => $manager->name ?? '',
                        'last_name' => $manager->last_name ?? null,
                        'last_name2' => $manager->last_name2 ?? null,
                        'nif_cif' => $manager->nif_cif ?? null,
                        'birthday' => $manager->birthday ?? null,
                        'email' => $manager->email,
                        'phone' => $manager->phone ?? null,
                        'comment' => $manager->comment ?? null,
                        'image' => $manager->image ?? null,
                        'status' => true,
                        'password' => bcrypt('password'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Actualizar el manager con el nuevo user_id
                    DB::table('managers_temp')->where('id', $manager->id)->update(['user_id' => $userId]);
                } else {
                    // Usar el usuario existente
                    DB::table('managers_temp')->where('id', $manager->id)->update(['user_id' => $existingUser->id]);
                }
            }
        }
    }

    /**
     * Migrar datos de sellers a users
     */
    private function migrateSellersToUsers()
    {
        $sellers = DB::table('sellers_temp')->get();
        
        foreach ($sellers as $seller) {
            if ($seller->email) {
                // Verificar si ya existe un usuario con ese email
                $existingUser = DB::table('users')->where('email', $seller->email)->first();
                
                if (!$existingUser) {
                    // Crear nuevo usuario con los datos del seller
                    $userId = DB::table('users')->insertGetId([
                        'name' => $seller->name ?? '',
                        'last_name' => $seller->last_name ?? null,
                        'last_name2' => $seller->last_name2 ?? null,
                        'nif_cif' => $seller->nif_cif ?? null,
                        'birthday' => $seller->birthday ?? null,
                        'email' => $seller->email,
                        'phone' => $seller->phone ?? null,
                        'comment' => $seller->comment ?? null,
                        'image' => $seller->image ?? null,
                        'status' => $seller->status ?? true,
                        'password' => bcrypt('password'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Actualizar el seller con el nuevo user_id
                    DB::table('sellers_temp')->where('id', $seller->id)->update(['user_id' => $userId]);
                } else {
                    // Usar el usuario existente
                    DB::table('sellers_temp')->where('id', $seller->id)->update(['user_id' => $existingUser->id]);
                }
            }
        }
    }

    /**
     * Migrar las relaciones
     */
    private function migrateRelations()
    {
        // Migrar sellers
        $sellers = DB::table('sellers_temp')->whereNotNull('user_id')->whereNotNull('entity_id')->get();
        
        foreach ($sellers as $seller) {
            DB::table('sellers')->insert([
                'user_id' => $seller->user_id,
                'entity_id' => $seller->entity_id,
                'created_at' => $seller->created_at,
                'updated_at' => $seller->updated_at,
            ]);
        }

        // Migrar managers
        $managers = DB::table('managers_temp')->whereNotNull('user_id')->get();
        
        foreach ($managers as $manager) {
            // Buscar la entidad asociada al manager usando el manager_id original
            $entity = DB::table('entities')->where('manager_id', $manager->id)->first();
            
            // Buscar la administración asociada al manager usando el manager_id original
            $administration = DB::table('administrations')->where('manager_id', $manager->id)->first();
            
            if ($entity) {
                DB::table('managers')->insert([
                    'user_id' => $manager->user_id,
                    'entity_id' => $entity->id,
                    'administration_id' => null,
                    'created_at' => $manager->created_at,
                    'updated_at' => $manager->updated_at,
                ]);
            } elseif ($administration) {
                DB::table('managers')->insert([
                    'user_id' => $manager->user_id,
                    'entity_id' => null,
                    'administration_id' => $administration->id,
                    'created_at' => $manager->created_at,
                    'updated_at' => $manager->updated_at,
                ]);
            }
        }
    }

    public function checkParticipation(Request $r)
    {
        // Validar que el parámetro 'ref' esté presente
        $ref = ParticipationTicketReference::normalize($r->query('ref'));
        if (!$ref) {
            return response()->json([
                'success' => false,
                'message' => 'El parámetro ref es obligatorio.'
            ], 400);
        }

        if (! ParticipationTicketReference::isValid($ref)) {
            return response()->json([
                'success' => false,
                'message' => 'La referencia no es válida (formato o dígito de control incorrecto).',
            ], 400);
        }

        // Buscar el set que contenga el ticket con la referencia 'r' igual a $ref
        $set = \App\Models\Set::whereNotNull('tickets')->with(['reserve.lottery'])->get()->first(function($set) use ($ref) {
            if (!is_array($set->tickets)) return false;
            foreach ($set->tickets as $ticket) {
                if (isset($ticket['r']) && $ticket['r'] == $ref) {
                    return true;
                }
            }
            return false;
        });

        if (!$set) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró ningún set con esa referencia.'
            ], 404);
        }

        // Retornar todos los datos del set, incluyendo reserve y reserve.lottery
        return response()->json([
            'success' => true,
            'set' => $set,
            'reserve' => $set->reserve,
            'entity' => $set->reserve ? $set->reserve->entity : null,
            'lottery' => $set->reserve ? $set->reserve->lottery : null
        ]);
    }

    public function showParticipationTicket(Request $request)
    {

        // Redirigir a la nueva URL externa manteniendo el parámetro ref
        // if ($request->has('ref')) {
        //     $ref = $request->query('ref');
        //     $redirectUrl = 'https://web.elbuholotero.es/loteria-empresas-parti.php?ref=' . urlencode($ref);
        //     return redirect($redirectUrl);
        // }
        
        // // Si no hay ref, redirigir sin parámetro
        // return redirect('https://web.elbuholotero.es/loteria-empresas-parti.php');
        
        $ticket = null;
        $error = null;
        $prizeInfo = null;
        
        if ($request->has('ref')) {
            $ref = $request->query('ref');
            
            // Buscar el set que contiene la referencia en tickets
            $set = \App\Models\Set::whereNotNull('tickets')
                ->with(['reserve.lottery', 'reserve.entity'])
                ->get()
                ->first(function($set) use ($ref) {
                    if (!is_array($set->tickets)) return false;
                    foreach ($set->tickets as $ticket) {
                        if (isset($ticket['r']) && $ticket['r'] == $ref) {
                            return true;
                        }
                    }
                    return false;
                });
            
            if (!$set) {
                $error = 'No se encontró ninguna participación con esa referencia.';
            } else {
                // Encontrar el número de participación correspondiente a la referencia
                $participationNumber = null;
                foreach ($set->tickets as $ticket) {
                    if (isset($ticket['r']) && $ticket['r'] == $ref) {
                        $participationNumber = $ticket['n'];
                            break;
                        }
                }
                
                /*\Log::info("Set Tickets: " . json_encode($set->tickets));
                \Log::info("Looking for ref: " . $ref);
                \Log::info("Found participation number: " . ($participationNumber ?? 'NULL'));*/
                
                // Buscar la participación por set_id y participation_number
                $participation = \App\Models\Participation::where('set_id', $set->id)
                    ->where('participation_number', $participationNumber)
                    ->first();

                    // return $participation;
                
                if (!$participation) {
                    $error = 'No se encontró la participación correspondiente a esa referencia.';
                } else if ($participation->status !== 'vendida' && $participation->status !== 'pagada') {
                    \Log::info("Participation Status: " . $participation->status);
                    $error = 'Esta participación no está asignada.';
                } else {
                    \Log::info("Participation Status: " . $participation->status . " (OK)");
                    $reserve = $set->reserve;
                    $lottery = $reserve->lottery;
                    
                    // Obtener los números ganadores desde reservation_numbers
                    $reservedNumbers = $reserve->reservation_numbers ?? [];
                    
                    // Manejar todos los números reservados como posibles ganadores
                    $winningNumbers = [];
                    
                    // Si reservation_numbers tiene solo 1 número, todas las participaciones
                    // del set tienen el mismo número ganador
                    if (count($reservedNumbers) == 1) {
                        $winningNumbers = $reservedNumbers;
                    } else {
                        // Si hay múltiples números, usar todos los números reservados
                        $winningNumbers = $reservedNumbers;
                    }
                    
                    /*\Log::info("Reserved Numbers: " . json_encode($reservedNumbers));
                    \Log::info("Participation Number: " . $participationNumber);
                    \Log::info("Winning Number Index: " . ($participationNumber - 1));
                    \Log::info("Reserved Numbers Count: " . count($reservedNumbers));
                    \Log::info("Winning Number: " . ($winningNumber ?? 'NULL'));*/
                
                // Debug: Log de información
                /*\Log::info("=== DEBUG PARTICIPATION TICKET ===");
                \Log::info("Winning Number: " . ($winningNumber ?? 'NULL'));
                \Log::info("Set ID: " . $set->id);
                \Log::info("Participation Number: " . $participationNumber);*/
                
                // Buscar en los resultados del escrutinio guardado para todos los números ganadores
                $scrutinyResults = [];
                $totalPrizeAmount = 0;
                $allWinningCategories = [];
                
                if (!empty($winningNumbers)) {
                    $scrutinyResults = DB::table('scrutiny_detailed_results')
                        ->join('administration_lottery_scrutinies', 'scrutiny_detailed_results.scrutiny_id', '=', 'administration_lottery_scrutinies.id')
                        ->whereIn('scrutiny_detailed_results.winning_number', $winningNumbers)
                        ->where('scrutiny_detailed_results.set_id', $set->id)
                        ->where('administration_lottery_scrutinies.is_scrutinized', true) // Buscar en escrutinios procesados, no solo guardados
                        ->select('scrutiny_detailed_results.*')
                        ->get();
                    
                    \Log::info("Scrutiny Results Found: " . $scrutinyResults->count());
                    
                    // Calcular premio total y categorías ganadoras
                    foreach ($scrutinyResults as $result) {
                        $totalPrizeAmount += $result->premio_por_participacion;
                        $categories = json_decode($result->winning_categories, true);
                        if (is_array($categories)) {
                            // Construir estructura correcta para la vista
                            foreach ($categories as $category) {
                                if (is_array($category) && isset($category['categoria']) && isset($category['premio_decimo'])) {
                                    $allWinningCategories[] = $category;
                                } elseif (is_string($category) && !empty(trim($category))) {
                                    // Si es solo un string, crear estructura básica
                                    $allWinningCategories[] = [
                                        'categoria' => $category,
                                        'premio_decimo' => $result->premio_por_decimo ?? 0
                                    ];
                                }
                            }
                        }
                    }
                }
                
                if ($scrutinyResults->count() > 0) {
                    // Usar datos del escrutinio guardado
                    $prizeInfo = [
                        'has_won' => true,
                        'prize_category' => 'Premio del Escrutinio',
                        'prize_amount' => $totalPrizeAmount,
                        'matching_numbers' => $winningNumbers,
                        'winning_categories' => $allWinningCategories,
                        'scrutiny_results_count' => $scrutinyResults->count()
                    ];
                } else {
                    // No hay premio en el escrutinio guardado
                    $prizeInfo = [
                        'has_won' => false,
                        'prize_category' => null,
                        'prize_amount' => 0,
                        'matching_numbers' => $winningNumbers,
                        'winning_categories' => []
                    ];
                }
                
                $ticket = [
                    'data' => [
                        'participation_code' => $participation->display_participation_code,
                        'participation_number' => $ref, // La referencia original buscada (000100061758806276046)
                        'numbers' => $reservedNumbers,
                        'winning_numbers' => $winningNumbers
                    ],
                    'set' => $set,
                    'reserve' => $reserve,
                    'lottery' => $lottery,
                    'prize_info' => $prizeInfo
                ];
                }
            }
        }
        
        return view('social.participation-ticket', compact('ticket', 'error'));
    }

    /**
     * Obtiene la info de premio para una referencia (misma lógica que comprobar-participacion).
     * Si el sorteo es a futuro, prize_amount = 0 y has_won = false (no mostrar nada en listado).
     * Devuelve ['has_won' => bool, 'prize_amount' => float, 'prize_category' => ?string].
     */
    public function getPrizeInfoForReference(string $ref): array
    {
        if ($ref === '') {
            return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
        }

        $set = \App\Models\Set::whereNotNull('tickets')
            ->with(['reserve.lottery'])
            ->get()
            ->first(function ($set) use ($ref) {
                if (!is_array($set->tickets)) return false;
                foreach ($set->tickets as $ticket) {
                    if (isset($ticket['r']) && $ticket['r'] === $ref) return true;
                }
                return false;
            });

        if (!$set) {
            return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
        }

        $participationNumber = null;
        foreach ($set->tickets as $ticket) {
            if (isset($ticket['r']) && $ticket['r'] === $ref) {
                $participationNumber = $ticket['n'] ?? null;
                break;
            }
        }
        if ($participationNumber === null) {
            return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
        }

        $participation = \App\Models\Participation::where('set_id', $set->id)
            ->where('participation_number', $participationNumber)
            ->first();
        if (!$participation) {
            return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
        }

        $reserve = $set->reserve;
        $lottery = $reserve ? $reserve->lottery : null;

        // Sorteo a futuro: no mostrar premio
        if ($lottery && $lottery->draw_date && $lottery->draw_date->isFuture()) {
            return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
        }

        $reservedNumbers = $reserve->reservation_numbers ?? [];
        $winningNumbers = is_array($reservedNumbers) ? $reservedNumbers : [];

        if (empty($winningNumbers)) {
            return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
        }

        $scrutinyResults = DB::table('scrutiny_detailed_results')
            ->join('administration_lottery_scrutinies', 'scrutiny_detailed_results.scrutiny_id', '=', 'administration_lottery_scrutinies.id')
            ->whereIn('scrutiny_detailed_results.winning_number', $winningNumbers)
            ->where('scrutiny_detailed_results.set_id', $set->id)
            ->where('administration_lottery_scrutinies.is_scrutinized', true)
            ->select('scrutiny_detailed_results.*')
            ->get();

        $totalPrizeAmount = 0;
        foreach ($scrutinyResults as $result) {
            $totalPrizeAmount += $result->premio_por_participacion;
        }

        if ($scrutinyResults->count() > 0 && $totalPrizeAmount > 0) {
            return [
                'has_won' => true,
                'prize_amount' => (float) $totalPrizeAmount,
                'prize_category' => 'Premio del Escrutinio',
            ];
        }

        return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
    }

    private function checkWinningNumbers($lottery, $ticketNumbers, $totalParticipations)
    {
        if (!$lottery->result || !$ticketNumbers) {
            return null;
        }

        $result = $lottery->result;
        $prizeInfo = [
            'has_won' => false,
            'prize_category' => null,
            'prize_amount' => 0,
            'matching_numbers' => []
        ];

        // Verificar cada número del ticket
        foreach ($ticketNumbers as $number) {
            $numberStr = str_pad($number, 5, '0', STR_PAD_LEFT);
            
            // Verificar primer premio
            if ($result->primer_premio && isset($result->primer_premio['decimo']) && $result->primer_premio['decimo'] == $numberStr) {
                $prizeInfo['has_won'] = true;
                $prizeInfo['prize_category'] = 'Primer Premio';
                $prizeInfo['prize_amount'] = ($result->primer_premio['prize'] ?? 0) / 100; // Convertir de céntimos a euros
                $prizeInfo['matching_numbers'][] = $number;
                continue;
            }
            
            // Verificar segundo premio
            if ($result->segundo_premio && isset($result->segundo_premio['decimo']) && $result->segundo_premio['decimo'] == $numberStr) {
                $prizeInfo['has_won'] = true;
                $prizeInfo['prize_category'] = 'Segundo Premio';
                $prizeInfo['prize_amount'] = ($result->segundo_premio['prize'] ?? 0) / 100;
                $prizeInfo['matching_numbers'][] = $number;
                continue;
            }
            
            // Verificar terceros premios
            if ($result->terceros_premios) {
                foreach ($result->terceros_premios as $premio) {
                    if (isset($premio['decimo']) && $premio['decimo'] == $numberStr) {
                        $prizeInfo['has_won'] = true;
                        $prizeInfo['prize_category'] = 'Tercer Premio';
                        $prizeInfo['prize_amount'] = ($premio['prize'] ?? 0) / 100;
                        $prizeInfo['matching_numbers'][] = $number;
                        break;
                    }
                }
            }
            
            // Verificar cuartos premios
            if ($result->cuartos_premios) {
                foreach ($result->cuartos_premios as $premio) {
                    if (isset($premio['decimo']) && $premio['decimo'] == $numberStr) {
                        $prizeInfo['has_won'] = true;
                        $prizeInfo['prize_category'] = 'Cuarto Premio';
                        $prizeInfo['prize_amount'] = ($premio['prize'] ?? 0) / 100;
                        $prizeInfo['matching_numbers'][] = $number;
                        break;
                    }
                }
            }
            
            // Verificar quintos premios
            if ($result->quintos_premios) {
                foreach ($result->quintos_premios as $premio) {
                    if (isset($premio['decimo']) && $premio['decimo'] == $numberStr) {
                        $prizeInfo['has_won'] = true;
                        $prizeInfo['prize_category'] = 'Quinto Premio';
                        $prizeInfo['prize_amount'] = ($premio['prize'] ?? 0) / 100;
                        $prizeInfo['matching_numbers'][] = $number;
                        break;
                    }
                }
            }

            // Verificar reintegros
            if ($result->reintegros) {
                foreach ($result->reintegros as $reintegro) {
                    if (isset($reintegro['decimo']) && $reintegro['decimo'] == substr($numberStr, -1)) {
                $prizeInfo['has_won'] = true;
                        $prizeInfo['prize_category'] = 'Reintegro';
                        $prizeInfo['prize_amount'] = ($reintegro['prize'] ?? 0) / 100;
                $prizeInfo['matching_numbers'][] = $number;
                        break;
                    }
                }
            }
        }

        return $prizeInfo;
    }

    public function checkDelete($type, $id)
    {
        $canDelete = true;
        $message = '';

        switch ($type) {
            case 'set':
                $set = \App\Models\Set::find($id);
                if ($set) {
                    $countBlocking = \App\Models\Participation::where('set_id', $set->id)
                        ->whereIn('status', ['asignada', 'vendida', 'pagada'])
                        ->count();
                    if ($countBlocking > 0) {
                        $canDelete = false;
                        $message = 'No se puede eliminar el set: hay participaciones asignadas o vendidas. Debe realizar la devolución de todas ellas antes de poder eliminar el set.';
                    } elseif ($set->designFormats()->count() > 0) {
                        $canDelete = false;
                        $message = 'El set no se puede borrar porque tiene diseños asociados.';
                    }
                }
                break;
            case 'reserve':
                $reserve = \App\Models\Reserve::find($id);
                if ($reserve) {
                    if ($reserve->sets()->count() > 0) {
                        $canDelete = false;
                        $message = 'La reserva no se puede borrar porque tiene sets asociados.';
                    }
                }
                break;
            case 'lottery':
                $lottery = \App\Models\Lottery::find($id);
                if ($lottery && $lottery->reserves()->exists()) {
                    $canDelete = false;
                    $message = 'El sorteo no se puede borrar porque tiene reservas asociadas.';
                }
                break;
            case 'entity':
                $entity = \App\Models\Entity::find($id);
                if ($entity) {
                    if ($entity->reserves()->count() > 0) {
                        $canDelete = false;
                        $message = 'La entidad no se puede borrar porque tiene reservas asociadas.';
                    } elseif ($entity->sellers()->count() > 0) {
                        $canDelete = false;
                        $message = 'La entidad no se puede borrar porque tiene vendedores asociados.';
                    }
                }
                break;
            case 'administration':
                $administration = \App\Models\Administration::find($id);
                if ($administration) {
                    if ($administration->entities()->count() > 0) {
                        $canDelete = false;
                        $message = 'La administración no se puede borrar porque tiene entidades asociadas.';
                    } elseif ($administration->manager()->whereHas('user')->exists()) {
                        $canDelete = false;
                        $message = 'La administración no se puede borrar porque tiene un gestor principal asociado.';
                    } elseif ($administration->lotteryScrutinies()->count() > 0) {
                        $canDelete = false;
                        $message = 'La administración no se puede borrar porque tiene escrutinios asociados.';
                    }
                }
                break;
            case 'user':
                // Para usuarios, quizás no hay restricciones, o verificar si es super_admin
                $user = \App\Models\User::find($id);
                if ($user && $user->role === 'super_admin') {
                    $canDelete = false;
                    $message = 'El usuario super administrador no se puede borrar.';
                }
                break;
            case 'lottery_type':
                $lotteryType = \App\Models\LotteryType::find($id);
                if ($lotteryType) {
                    if ($lotteryType->lotteries()->count() > 0) {
                        $canDelete = false;
                        $message = 'El tipo de lotería no se puede borrar porque tiene sorteos asociados.';
                    }
                }
                break;
            default:
                $canDelete = false;
                $message = 'Tipo no reconocido.';
        }

        return response()->json(['can_delete' => $canDelete, 'message' => $message]);
    }

    public function deleteItem($type, $id)
    {
        switch ($type) {
            case 'set': {
                $set = \App\Models\Set::find($id);
                if (!$set) {
                    return response()->json(['success' => false, 'message' => 'Set no encontrado.'], 404);
                }
                $countBlocking = \App\Models\Participation::where('set_id', $set->id)
                    ->whereIn('status', ['asignada', 'vendida', 'pagada'])
                    ->count();
                if ($countBlocking > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar el set: hay participaciones asignadas o vendidas. Debe realizar la devolución de todas ellas antes de poder eliminar el set.'
                    ], 422);
                }
                $set->delete();
                break;
            }
            case 'reserve':
                \App\Models\Reserve::find($id)->delete();
                break;
            case 'lottery': {
                $lottery = \App\Models\Lottery::find($id);
                if (!$lottery) {
                    return response()->json(['success' => false, 'message' => 'Sorteo no encontrado.'], 404);
                }
                $lottery->delete();
                break;
            }
            case 'entity':
                \App\Models\Entity::find($id)->delete();
                break;
            case 'administration':
                \App\Models\Administration::find($id)->delete();
                break;
            case 'user':
                \App\Models\User::find($id)->delete();
                break;
            case 'lottery_type':
                \App\Models\LotteryType::find($id)->delete();
                break;
        }

        return response()->json(['success' => true]);
    }
}