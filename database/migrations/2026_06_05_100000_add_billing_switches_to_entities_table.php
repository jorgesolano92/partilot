<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->boolean('entity_pays_management_fee')->default(false)->after('billing_iban');
            $table->boolean('entity_pays_print_fee')->default(false)->after('entity_pays_management_fee');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['entity_pays_management_fee', 'entity_pays_print_fee']);
        });
    }
};
