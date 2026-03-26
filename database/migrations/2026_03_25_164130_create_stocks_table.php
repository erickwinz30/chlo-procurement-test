<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->text('specification')->nullable();
            $table->string('category')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('unit');
            $table->integer('min_stock')->default(0); // threshold untuk alert
            $table->integer('max_stock')->nullable();
            $table->decimal('last_purchase_price', 15, 2)->nullable();
            $table->string('location')->nullable(); // lokasi penyimpanan
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexing
            $table->index('item_name');
            $table->index('category');
            $table->index(['item_name', 'specification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
