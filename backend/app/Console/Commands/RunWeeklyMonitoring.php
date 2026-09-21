<?php

namespace App\Console\Commands;

use App\Models\MonitoringRun;
use App\Models\Shop;
use App\Services\AuditEngine;
use Illuminate\Console\Command;

/**
 * F-08 Weekly CRO Monitoring: re-scans every installed shop, records the
 * score delta, and leaves a MonitoringRun row for the dashboard/email job
 * to notify from. Notification delivery (email) is a Phase 4 item.
 */
class RunWeeklyMonitoring extends Command
{
    protected $signature = 'vantora:run-weekly-monitoring';

    protected $description = 'Re-scan every active shop and record score changes for weekly monitoring.';

    public function handle(AuditEngine $engine): int
    {
        $shops = Shop::query()->whereNull('uninstalled_at')->get();

        $this->info("Monitoring {$shops->count()} shop(s).");

        foreach ($shops as $shop) {
            $previousScore = $shop->audits()->where('status', 'completed')->latest('id')->value('score_total');

            $audit = $engine->run($shop);

            MonitoringRun::query()->create([
                'shop_id' => $shop->id,
                'score_before' => $previousScore,
                'score_after' => $audit->score_total,
                'changes' => [
                    'new_issue_codes' => $audit->issues->pluck('code'),
                ],
            ]);
        }

        return self::SUCCESS;
    }
}
