<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'client_type')) {
                $table->string('client_type', 32)->default('legal_entity')->after('comments');
            }
            if (! Schema::hasColumn('entities', 'signer_name')) {
                $table->string('signer_name', 255)->nullable()->after('client_type');
            }
            if (! Schema::hasColumn('entities', 'signer_last_name')) {
                $table->string('signer_last_name', 255)->nullable()->after('signer_name');
            }
            if (! Schema::hasColumn('entities', 'signer_last_name2')) {
                $table->string('signer_last_name2', 255)->nullable()->after('signer_last_name');
            }
            if (! Schema::hasColumn('entities', 'signer_nif')) {
                $table->string('signer_nif', 20)->nullable()->after('signer_last_name2');
            }
            if (! Schema::hasColumn('entities', 'signer_email')) {
                $table->string('signer_email', 255)->nullable()->after('signer_nif');
            }
            if (! Schema::hasColumn('entities', 'signer_birthday')) {
                $table->date('signer_birthday')->nullable()->after('signer_email');
            }
            if (! Schema::hasColumn('entities', 'signer_is_primary_manager')) {
                $table->boolean('signer_is_primary_manager')->default(true)->after('signer_birthday');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            foreach ([
                'client_type',
                'signer_name',
                'signer_last_name',
                'signer_last_name2',
                'signer_nif',
                'signer_email',
                'signer_birthday',
                'signer_is_primary_manager',
            ] as $column) {
                if (Schema::hasColumn('entities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
