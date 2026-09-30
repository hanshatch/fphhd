<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memoria de comercios: qué categoría le corresponde a cada comercio
 * (OXXO → Súper, Telmex → Teléfono). Se alimenta sola cada vez que se
 * guarda un movimiento con categoría y se consulta al importar capturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_rules', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_key', 120)->unique();   // descripción normalizada
            $table->string('sample', 200);                    // último texto original visto
            $table->enum('type', ['expense', 'income'])->default('expense');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_rules');
    }
};
