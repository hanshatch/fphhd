<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->enum('kind', ['income', 'expense']);
            $table->string('color', 7)->default('#6366f1');
            $table->string('icon', 50)->default('tag');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['parent_id', 'name', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
