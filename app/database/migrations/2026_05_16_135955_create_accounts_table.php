<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['debit', 'credit', 'savings', 'investment', 'cash']);
            $table->enum('institution', ['banamex', 'mercadopago', 'nu', 'revolut', 'other'])->default('other');
            $table->string('currency', 3)->default('MXN');
            $table->decimal('initial_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('color', 7)->default('#6366f1');
            $table->string('icon', 50)->default('bank');
            $table->decimal('invest_apr', 5, 2)->nullable()->comment('Tasa nominal anual estimada');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
