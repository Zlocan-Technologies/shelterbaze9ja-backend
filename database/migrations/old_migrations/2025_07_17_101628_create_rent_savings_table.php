<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rent_savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->nullable()->constrained()->onDelete('set null');
            $table->string('plan_name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->date('due_date');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->decimal('early_withdrawal_penalty', 5, 2)->default(5.00); // 5%
            $table->decimal('deposit_charge', 5, 2)->default(2.00); // 2%
            $table->boolean('is_external_property')->default(false);
            $table->text('external_property_details')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rent_savings');
    }
};