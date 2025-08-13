<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rent_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_agreement_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_type', ['online', 'offline']);
            $table->string('bank_account_number')->nullable(); // For manual transfers
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('payment_proof_url');
            $table->date('payment_date');
            $table->date('due_date');
            $table->date('next_due_date')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['rental_agreement_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('payment_date');
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rent_payments');
    }
};
