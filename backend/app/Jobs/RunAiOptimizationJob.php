<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Models\AiUsage;
use App\Services\AiProviderClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * F-19 AI Product Optimizer. Nothing this generates publishes automatically
 * -- the job only writes to ai_jobs.output; a merchant has to approve, edit
 * or discard from the admin before anything touches the live product.
 */
class RunAiOptimizationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(protected int $aiJobId)
    {
    }

    public function handle(AiProviderClient $client): void
    {
        $job = AiJob::query()->findOrFail($this->aiJobId);
        $job->update(['status' => 'running']);

        try {
            $result = $client->generateProductCopy($job->input ?? []);

            $job->update([
                'status' => 'completed',
                'output' => $result['output'],
                'tokens' => $result['tokens'],
            ]);

            $period = now()->format('Y-m');
            $usage = AiUsage::query()->firstOrCreate(
                ['shop_id' => $job->shop_id, 'period' => $period],
                ['count' => 0]
            );
            $usage->increment('count');
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'output' => ['error' => $e->getMessage()]]);
        }
    }
}
