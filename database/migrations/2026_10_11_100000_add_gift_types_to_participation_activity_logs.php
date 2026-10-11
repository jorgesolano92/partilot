<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE participation_activity_logs MODIFY COLUMN activity_type ENUM(
            'created', 'assigned', 'returned_by_seller', 'sold', 'returned_to_administration',
            'status_changed', 'cancelled', 'modified', 'paid',
            'gift_sent', 'gift_accepted', 'gift_rejected', 'gift_expired', 'gift_cancelled'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::table('participation_activity_logs')
            ->whereIn('activity_type', ['gift_sent', 'gift_accepted', 'gift_rejected', 'gift_expired', 'gift_cancelled'])
            ->delete();

        DB::statement("ALTER TABLE participation_activity_logs MODIFY COLUMN activity_type ENUM(
            'created', 'assigned', 'returned_by_seller', 'sold', 'returned_to_administration',
            'status_changed', 'cancelled', 'modified', 'paid'
        ) NOT NULL");
    }
};
