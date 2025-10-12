<?php

namespace App\Jobs;

use App\Models\RentalAgreement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckExpiredAgreementJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        RentalAgreement::where('agreement_end_date', '<', now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);
    }
}
