<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('property_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->json('verification_images'); // URLs of verification photos
            $table->text('verification_notes');
            $table->decimal('longitude', 15, 8); // Changed from 10,8 to 15,8 to support -180 to 180
            $table->decimal('latitude', 15, 8);  // Changed from 10,8 to 15,8 to support -90 to 90
            $table->timestamp('verification_date');
            $table->enum('status', ['verified', 'rejected']);
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['property_id', 'status']);
            $table->index('agent_id');
            $table->index('verification_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_verifications');
    }
};
