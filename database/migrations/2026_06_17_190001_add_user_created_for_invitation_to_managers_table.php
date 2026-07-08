<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            if (! Schema::hasColumn('managers', 'user_created_for_invitation')) {
                $table->boolean('user_created_for_invitation')->default(false)->after('requires_password_setup');
            }
        });
    }

    public function down(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            if (Schema::hasColumn('managers', 'user_created_for_invitation')) {
                $table->dropColumn('user_created_for_invitation');
            }
        });
    }
};
