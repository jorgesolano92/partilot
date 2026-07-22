<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->decimal('cut_lines', 8, 2)->nullable()->after('identation');
        });
    }

    public function down(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->dropColumn('cut_lines');
        });
    }
};
