<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('agent_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('landlord_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('property_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('assignment_type', ['landlord_support', 'property_verification']);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade'); // admin
            $table->text('assignment_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['agent_id', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index(['property_id', 'assignment_type']);
            $table->index('assignment_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_assignments');
    }
};

