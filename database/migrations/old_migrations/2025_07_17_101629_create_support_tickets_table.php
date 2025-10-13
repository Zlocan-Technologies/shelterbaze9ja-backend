<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('rental_agreement_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ticket_number')->unique();
            $table->enum('ticket_type', ['general', 'property_issue', 'payment_issue', 'technical', 'account_issue']);
            $table->string('subject');
            $table->text('description');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->json('attachments')->nullable(); // URLs of attached files
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('ticket_number');
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });
    }

    public function down()
    {
        Schema::dropIfExists('support_tickets');
    }
};