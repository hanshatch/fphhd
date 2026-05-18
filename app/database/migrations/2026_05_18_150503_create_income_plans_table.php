<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();

            // Dónde cae el ingreso
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            // Estimado
            $table->decimal('expected_amount', 14, 2)->comment('Monto estimado por periodo');
            $table->enum('frequency', ['biweekly', 'monthly', 'weekly'])->default('biweekly');

            // Para quincenales: día 1 y día 2 del mes (ej. 15 y 30)
            $table->unsignedTinyInteger('day_1')->nullable()->comment('Primer día del mes');
            $table->unsignedTinyInteger('day_2')->nullable()->comment('Segundo día (para quincenales)');

            // Control
            $table->date('next_expected_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['next_expected_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_plans');
    }
};
