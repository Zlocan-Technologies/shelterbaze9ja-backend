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
            $table->decimal('longitude', 10, 8);
            $table->decimal('latitude', 10, 8);
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
