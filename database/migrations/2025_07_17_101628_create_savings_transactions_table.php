<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_id')->constrained('rent_savings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('charge_amount', 15, 2)->default(0); // 2% deposit charge
            $table->decimal('penalty_amount', 15, 2)->default(0); // Early withdrawal penalty
            $table->decimal('net_amount', 15, 2); // Amount after charges/penalties
            $table->enum('transaction_type', ['deposit', 'withdrawal']);
            $table->boolean('is_early_withdrawal')->default(false);
            $table->string('payment_reference')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->enum('payment_method', ['paystack', 'bank_transfer'])->default('paystack');
            $table->json('payment_data')->nullable(); // Store payment provider response
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['savings_id', 'transaction_type']);
            $table->index(['user_id', 'status']);
            $table->index('payment_reference');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('savings_transactions');
    }
};
