<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Campos para vendedores externos (replicando users)
            $table->string('last_name')->nullable()->after('name');
            $table->string('last_name2')->nullable()->after('last_name');
            $table->string('nif_cif')->nullable()->after('last_name2');
            $table->date('birthday')->nullable()->after('nif_cif');
            
            // Campo para diferenciar tipo de vendedor
            $table->enum('seller_type', ['partilot', 'externo'])->default('partilot')->after('status');
            
            // Comentarios adicionales
            $table->text('comment')->nullable()->after('seller_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'last_name2', 
                'nif_cif',
                'birthday',
                'seller_type',
                'comment'
            ]);
        });
    }
};
