<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('biometric_data')->nullable()->after('fcm_token');
            $table->boolean('biometric_enabled')->default(false)->after('biometric_data');
            $table->timestamp('biometric_enrolled_at')->nullable()->after('biometric_enabled');
            $table->string('device_id')->nullable()->after('biometric_enrolled_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'biometric_data',
                'biometric_enabled',
                'biometric_enrolled_at',
                'device_id'
            ]);
        });
    }
};