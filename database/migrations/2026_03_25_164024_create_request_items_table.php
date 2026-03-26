<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->text('specification')->nullable();
            $table->string('category')->nullable(); // e.g., ATK, Elektronik, Furniture
            $table->integer('quantity');
            $table->string('unit'); // pcs, box, rim, etc
            $table->decimal('estimated_price', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexing
            $table->index('request_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
