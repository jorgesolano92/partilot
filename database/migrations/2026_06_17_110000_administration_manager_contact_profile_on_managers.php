<?php

use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            if (! Schema::hasColumn('managers', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('contact_email');
            }
            if (! Schema::hasColumn('managers', 'contact_last_name')) {
                $table->string('contact_last_name')->nullable()->after('contact_name');
            }
            if (! Schema::hasColumn('managers', 'contact_last_name2')) {
                $table->string('contact_last_name2')->nullable()->after('contact_last_name');
            }
            if (! Schema::hasColumn('managers', 'contact_nif_cif')) {
                $table->string('contact_nif_cif', 20)->nullable()->after('contact_last_name2');
            }
            if (! Schema::hasColumn('managers', 'contact_birthday')) {
                $table->date('contact_birthday')->nullable()->after('contact_nif_cif');
            }
            if (! Schema::hasColumn('managers', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable()->after('contact_birthday');
            }
            if (! Schema::hasColumn('managers', 'contact_comment')) {
                $table->text('contact_comment')->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('managers', 'contact_image')) {
                $table->string('contact_image')->nullable()->after('contact_comment');
            }
        });

        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'managers'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        foreach ($foreignKeys as $foreignKey) {
            Schema::table('managers', function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey->CONSTRAINT_NAME);
            });
        }

        // Referencias huérfanas (usuario borrado) impiden recrear la FK.
        DB::table('managers')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', User::query()->select('id'))
            ->update(['user_id' => null]);

        $primaryAdminManagers = Manager::query()
            ->whereNotNull('administration_id')
            ->whereNull('entity_id')
            ->where('is_primary', true)
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        foreach ($primaryAdminManagers as $manager) {
            $user = $manager->user;
            if (! $user || $user->isPanelAccount()) {
                continue;
            }

            $manager->update([
                'contact_email' => $manager->contact_email ?: $user->email,
                'contact_name' => $user->name,
                'contact_last_name' => $user->last_name,
                'contact_last_name2' => $user->last_name2,
                'contact_nif_cif' => $user->nif_cif,
                'contact_birthday' => $user->birthday,
                'contact_phone' => $user->phone,
                'contact_comment' => $user->comment,
                'contact_image' => $user->image,
                'user_id' => null,
            ]);

            $otherManagers = Manager::query()->where('user_id', $user->id)->exists();
            $hasSellers = Seller::query()->where('user_id', $user->id)->exists();

            if (! $otherManagers && ! $hasSellers) {
                $user->delete();
            }
        }

        Schema::table('managers', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        $hasUserForeign = DB::selectOne(
            "SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'managers'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME = 'users'
            LIMIT 1"
        );

        if (! $hasUserForeign) {
            Schema::table('managers', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('managers', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('managers', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name',
                'contact_last_name',
                'contact_last_name2',
                'contact_nif_cif',
                'contact_birthday',
                'contact_phone',
                'contact_comment',
                'contact_image',
            ]);
        });
    }
};
