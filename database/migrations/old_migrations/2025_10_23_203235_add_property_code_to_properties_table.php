<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_code', 20)->unique()->nullable()->after('id');
        });

        // Generate property codes for existing properties
        $properties = DB::table('properties')->whereNull('property_code')->get();
        foreach ($properties as $property) {
            $code = 'SHP' . str_pad($property->id, 3, '0', STR_PAD_LEFT);
            DB::table('properties')->where('id', $property->id)->update(['property_code' => $code]);
        }

        // Make property_code not nullable after populating
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_code', 20)->unique()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('property_code');
        });
    }
};
