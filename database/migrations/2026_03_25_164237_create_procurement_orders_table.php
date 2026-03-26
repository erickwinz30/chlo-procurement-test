<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('po_number')->unique(); // PO-YYYYMM-XXXX
            $table->enum('status', [
                'draft',
                'sent',
                'confirmed',
                'shipped',
                'received',
                'completed',
                'cancelled'
            ])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->date('order_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexing
            $table->index('po_number');
            $table->index('request_id');
            $table->index('vendor_id');
            $table->index('status');
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_orders');
    }
};
