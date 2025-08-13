<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->text('description')->nullable();
            $table->enum('type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->boolean('is_public')->default(false); // Whether setting can be accessed by frontend
            $table->timestamps();
            
            // Indexes
            $table->index('key');
            $table->index('is_public');
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_settings');
    }
};