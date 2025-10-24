<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userIds;
    protected $title;
    protected $body;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $userIds, string $title, string $body, array $data = [])
    {
        $this->userIds = $userIds;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcmService): void
    {
        try {
            Log::info('Processing push notification job', [
                'user_count' => count($this->userIds),
                'title' => $this->title
            ]);

            $result = $fcmService->sendToUsers($this->userIds, $this->title, $this->body, $this->data);

            Log::info('Push notification job completed', [
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Push notification job failed', [
                'user_ids' => $this->userIds,
                'title' => $this->title,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Push notification job permanently failed', [
            'user_ids' => $this->userIds,
            'title' => $this->title,
            'error' => $exception->getMessage()
        ]);
    }
}
