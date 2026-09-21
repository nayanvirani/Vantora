<?php

namespace App\Mail;

use App\Models\MonitoringRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * F-08 Weekly CRO Monitoring email. Basic on both plans per spec section 3
 * ("Weekly monitoring: Yes (basic email)" for Starter, "Yes (advanced
 * later)" for Pro) -- so both plans get the same email for now.
 */
class WeeklyMonitoringMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MonitoringRun $run)
    {
    }

    public function envelope(): Envelope
    {
        $delta = ($this->run->score_after ?? 0) - ($this->run->score_before ?? 0);
        $direction = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'unchanged');

        return new Envelope(
            subject: "Your Vantora Health Score is {$direction} this week",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-monitoring',
        );
    }
}
