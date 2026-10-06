<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participation_donations')) {
            return;
        }

        Schema::table('participation_donations', function (Blueprint $table) {
            if (! Schema::hasColumn('participation_donations', 'entity_id')) {
                $table->foreignId('entity_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('participation_donations', 'certificado_fiscal')) {
                $table->boolean('certificado_fiscal')->default(false)->after('anonima');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('participation_donations')) {
            return;
        }

        Schema::table('participation_donations', function (Blueprint $table) {
            if (Schema::hasColumn('participation_donations', 'certificado_fiscal')) {
                $table->dropColumn('certificado_fiscal');
            }
            if (Schema::hasColumn('participation_donations', 'entity_id')) {
                $table->dropConstrainedForeignId('entity_id');
            }
        });
    }
};
