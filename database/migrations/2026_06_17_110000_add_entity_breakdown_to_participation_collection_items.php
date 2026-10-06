<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participation_collection_items')) {
            return;
        }

        Schema::table('participation_collection_items', function (Blueprint $table) {
            if (! Schema::hasColumn('participation_collection_items', 'entity_id')) {
                $table->foreignId('entity_id')->nullable()->after('participation_id')
                    ->constrained('entities')->nullOnDelete();
            }
            if (! Schema::hasColumn('participation_collection_items', 'amount')) {
                $table->decimal('amount', 12, 2)->nullable()->after('entity_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('participation_collection_items')) {
            return;
        }

        Schema::table('participation_collection_items', function (Blueprint $table) {
            if (Schema::hasColumn('participation_collection_items', 'entity_id')) {
                $table->dropForeign(['entity_id']);
                $table->dropColumn('entity_id');
            }
            if (Schema::hasColumn('participation_collection_items', 'amount')) {
                $table->dropColumn('amount');
            }
        });
    }
};
