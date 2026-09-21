<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\AuditEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAuditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 110; // acceptance target: audit completes within 2 minutes

    public function __construct(protected int $shopId)
    {
    }

    public function handle(AuditEngine $engine): void
    {
        $shop = Shop::query()->findOrFail($this->shopId);

        $engine->run($shop);
    }
}
