<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->string('design_name', 120)->nullable()->after('set_id');
            $table->boolean('back_skipped')->default(false)->after('back_html');
        });
    }

    public function down(): void
    {
        Schema::table('design_formats', function (Blueprint $table) {
            $table->dropColumn(['design_name', 'back_skipped']);
        });
    }
};
