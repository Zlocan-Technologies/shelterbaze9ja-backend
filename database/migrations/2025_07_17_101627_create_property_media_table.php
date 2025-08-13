<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->enum('media_type', ['image', 'video']);
            $table->string('media_url');
            $table->string('public_id')->nullable(); // Cloudinary public ID
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            // Indexes
            $table->index('property_id');
            $table->index(['property_id', 'media_type']);
            $table->index(['property_id', 'is_primary']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_media');
    }
};