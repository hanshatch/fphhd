<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_charges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Cuenta donde se aplica el cargo
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            // Tipo y monto
            $table->enum('type', ['expense', 'income'])->default('expense');
            $table->decimal('amount', 14, 2)->comment('Monto por aplicación (cuota mensual)');

            // Cuándo aplicar
            $table->unsignedTinyInteger('day_of_month')->comment('Día del mes 1-31');
            $table->date('start_date')->comment('Fecha de la primera aplicación');
            $table->date('end_date')->nullable()->comment('Null = indefinido');

            // Control de MSI (meses sin intereses)
            $table->boolean('is_msi')->default(false)->comment('Es plan de meses sin intereses');
            $table->unsignedSmallInteger('total_installments')->nullable()->comment('Null = indefinido');
            $table->unsignedSmallInteger('applied_installments')->default(0);
            $table->decimal('original_amount', 14, 2)->nullable()->comment('Monto total de la compra MSI');

            // Control de ejecución
            $table->date('next_application_date')->comment('Próxima fecha de aplicación automática');
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['next_application_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_charges');
    }
};
