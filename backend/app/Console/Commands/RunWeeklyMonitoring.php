<?php

namespace App\Console\Commands;

use App\Mail\WeeklyMonitoringMail;
use App\Models\MonitoringRun;
use App\Models\Shop;
use App\Services\AuditEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * F-08 Weekly CRO Monitoring: re-scans every installed shop, records the
 * score delta, and emails the merchant (both plans get the basic email
 * per spec section 3 -- Pro's "advanced" monitoring is a later addition).
 * Runs as its own Railway cron service (Vantora-monitoring), not through
 * Laravel's scheduler -- see routes/console.php.
 */
class RunWeeklyMonitoring extends Command
{
    protected $signature = 'vantora:run-weekly-monitoring';

    protected $description = 'Re-scan every active shop, record score changes, and email the merchant.';

    public function handle(AuditEngine $engine): int
    {
        $shops = Shop::query()->whereNull('uninstalled_at')->get();

        $this->info("Monitoring {$shops->count()} shop(s).");

        foreach ($shops as $shop) {
            $previousScore = $shop->audits()->where('status', 'completed')->latest('id')->value('score_total');

            $audit = $engine->run($shop);

            $run = MonitoringRun::query()->create([
                'shop_id' => $shop->id,
                'score_before' => $previousScore,
                'score_after' => $audit->score_total,
                'changes' => [
                    'new_issue_codes' => $audit->issues->pluck('code'),
                ],
            ]);

            if ($shop->email) {
                Mail::to($shop->email)->send(new WeeklyMonitoringMail($run));
                $run->update(['notified_at' => now()]);
            }
        }

        return self::SUCCESS;
    }
}
