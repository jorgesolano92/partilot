<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'signer_email')) {
                $after = Schema::hasColumn('entities', 'signer_nif') ? 'signer_nif' : 'signer_last_name2';
                $table->string('signer_email', 255)->nullable()->after($after);
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'signer_email')) {
                $table->dropColumn('signer_email');
            }
        });
    }
};
