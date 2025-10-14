<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('property_id');
            $table->unique(['user_id', 'property_id']); // One favorite per user per property
        });
    }

    public function down()
    {
        Schema::dropIfExists('favorites');
    }
};
