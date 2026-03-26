<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // pemohon
            $table->foreignId('department_id')->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', [
                'draft',
                'submitted',
                'verified',
                'approved',
                'rejected',
                'stock_check',
                'in_stock',
                'in_procurement',
                'completed',
                'cancelled'
            ])->default('draft');
            $table->date('needed_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('request_number')->unique(); // Format: REQ-YYYYMM-XXXX
            $table->timestamps();
            $table->softDeletes();

            // Indexing
            $table->index('status');
            $table->index('user_id');
            $table->index('department_id');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
