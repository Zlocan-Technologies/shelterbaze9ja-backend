<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('title');
            $table->text('description');
            $table->enum('property_type', ['1_bedroom', '2_bedroom', '3_bedroom', '4_bedroom', 'studio', 'duplex', 'bungalow']);
            $table->decimal('rent_amount', 15, 2);
            $table->decimal('shelterbaze_commission', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('location_address');
            $table->string('state');
            $table->string('lga');
            $table->decimal('longitude', 10, 8)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->json('facilities')->nullable(); // car_park, swimming_pool, etc.
            $table->enum('status', ['open', 'closed', 'rented'])->default('open');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['landlord_id', 'status']);
            $table->index(['state', 'lga']);
            $table->index(['property_type', 'status']);
            $table->index(['rent_amount', 'status']);
            $table->index('verification_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('properties');
    }
};