<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de presupuestos eliminado (decisión de producto 2026-07-17):
     * Hans no lo usa ni lo va a usar. La tabla estaba vacía en local y prod.
     */
    public function up(): void
    {
        Schema::dropIfExists('budgets');
    }

    public function down(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique('category_id');
        });
    }
};
