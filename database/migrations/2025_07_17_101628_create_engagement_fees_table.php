<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('engagement_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('payment_reference')->unique();
            $table->enum('payment_status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('payment_method')->default('paystack');
            $table->json('payment_data')->nullable(); // Store Paystack response
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'property_id']);
            $table->index('payment_reference');
            $table->index('payment_status');
            $table->unique(['user_id', 'property_id']); // One engagement fee per user per property
        });
    }

    public function down()
    {
        Schema::dropIfExists('engagement_fees');
    }
};
