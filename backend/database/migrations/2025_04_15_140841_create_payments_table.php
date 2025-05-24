<?php
// database/migrations/2025_05_24_103000_create_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_subscription_id')->nullable()->constrained()->onDelete('set null');
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MAD');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('payment_method');
            $table->json('payment_data')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
