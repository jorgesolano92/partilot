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
        Schema::create('scrutiny_entity_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administration_lottery_scrutiny_id')
                ->constrained('administration_lottery_scrutinies', 'id', 'scr_ent_admin_lottery_fk')
                ->onDelete('cascade');
            $table->foreignId('entity_id')->constrained('entities')->onDelete('cascade');
            
            // Datos de las reservas de la entidad
            $table->json('reserved_numbers')->nullable(); // Números reservados por la entidad
            $table->integer('total_reserved')->default(0); // Total de números reservados
            $table->integer('total_issued')->default(0); // Total emitidos
            $table->integer('total_sold')->default(0); // Total vendidos
            $table->integer('total_returned')->default(0); // Total devueltos
            
            // Resultados del escrutinio
            $table->json('winning_numbers')->nullable(); // Números ganadores de esta entidad
            $table->integer('total_winning')->default(0); // Total de números premiados
            $table->decimal('total_prize_amount', 15, 2)->default(0); // Importe total de premios
            $table->decimal('prize_per_participation', 10, 2)->default(0); // Premio por participación
            
            // Detalles de premios por categoría
            $table->json('prize_breakdown')->nullable(); // Desglose de premios: {tipo_premio: {numeros: [], importe: 0}}
            
            $table->timestamps();
            
            // Índices
            $table->index(['administration_lottery_scrutiny_id', 'entity_id']);
            $table->index(['entity_id', 'total_winning']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrutiny_entity_results');
    }
};
