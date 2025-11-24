<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->decimal('management_fee', 15, 2)->default(0)->after('shelterbaze_commission');
            $table->decimal('landlord_payout', 15, 2)->default(0)->after('management_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->dropColumn(['management_fee', 'landlord_payout']);
        });
    }
};
