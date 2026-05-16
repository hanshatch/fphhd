<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('type', ['income', 'expense', 'transfer', 'interest', 'fee']);
            $table->decimal('amount', 14, 2);
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            // Solo para transferencias internas: cuenta destino
            $table->foreignId('counterparty_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['date', 'account_id']);
            $table->index(['date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
