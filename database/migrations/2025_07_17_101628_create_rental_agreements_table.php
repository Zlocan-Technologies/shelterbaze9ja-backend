<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rental_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('rent_amount', 15, 2);
            $table->decimal('shelterbaze_commission', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->date('agreement_start_date');
            $table->date('agreement_end_date');
            $table->enum('status', ['pending', 'active', 'expired', 'terminated'])->default('pending');
            $table->text('terms_conditions')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['property_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index('agreement_start_date');
            $table->index('agreement_end_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rental_agreements');
    }
};