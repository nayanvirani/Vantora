<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunAuditJob;
use App\Models\Audit;
use App\Models\AuditIssue;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function store(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $audit = Audit::query()->create([
            'shop_id' => $shop->id,
            'status' => 'queued',
        ]);

        RunAuditJob::dispatch($shop->id);

        return response()->json($audit, 202);
    }

    public function latest(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $audit = Audit::query()
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->with(['scores', 'issues'])
            ->first();

        return response()->json($audit);
    }

    public function show(Request $request, Audit $audit)
    {
        $this->authorizeShop($request, $audit);

        return response()->json($audit->load(['scores', 'issues']));
    }

    public function dismissIssue(Request $request, Audit $audit, AuditIssue $issue)
    {
        $this->authorizeShop($request, $audit);
        abort_unless($issue->audit_id === $audit->id, 404);

        $issue->update(['status' => 'dismissed']);

        return response()->json($issue);
    }

    public function snoozeIssue(Request $request, Audit $audit, AuditIssue $issue)
    {
        $this->authorizeShop($request, $audit);
        abort_unless($issue->audit_id === $audit->id, 404);

        $issue->update(['status' => 'snoozed']);

        return response()->json($issue);
    }

    protected function authorizeShop(Request $request, Audit $audit): void
    {
        $shop = $request->attributes->get('shop');

        abort_unless($audit->shop_id === $shop->id, 404);
    }
}
