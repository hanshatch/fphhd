<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metas de ahorro: proyectos, viajes y compras planeadas. Cada meta tiene un
 * desglose de costos opcional y un registro de lo apartado (no son
 * movimientos: el dinero sigue en su cuenta, solo se marca como comprometido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('kind', 20)->default('trip')->comment('trip, project, purchase, event, other');
            $table->decimal('target_amount', 14, 2)->comment('Costo total; si hay desglose, es la suma de las partidas');
            $table->date('target_date')->comment('Fecha en que se necesita el dinero');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete()
                ->comment('Cuenta donde se guarda lo apartado (informativa)');
            $table->string('color', 7)->default('#76a72b');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->comment('active, completed');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'target_date']);
        });

        Schema::create('goal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->string('description', 150);
            $table->decimal('amount', 14, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2)->comment('Positivo = aparté, negativo = retiré');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['goal_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
        Schema::dropIfExists('goal_items');
        Schema::dropIfExists('goals');
    }
};
