<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->integer('level'); // level approval (1, 2, 3, etc)
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexing
            $table->index('request_id');
            $table->index('approver_id');
            $table->index('status');
            $table->unique(['request_id', 'approver_id', 'level']); // Prevent duplicate approval
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
