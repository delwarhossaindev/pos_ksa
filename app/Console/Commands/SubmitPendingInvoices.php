<?php

namespace App\Console\Commands;

use App\Services\Zatca\ZatcaService;
use Illuminate\Console\Command;

/**
 * Sends ZATCA the e-invoices still waiting (simplified invoices are due within 24 hours).
 * Scheduled hourly in routes/console.php; needs the cPanel cron that runs `schedule:run`.
 */
class SubmitPendingInvoices extends Command
{
    protected $signature = 'zatca:submit-pending {--all : Also resend invoices ZATCA rejected (after fixing what it rejected them for)}';

    protected $description = 'Report / clear e-invoices that ZATCA has not accepted yet';

    public function handle(ZatcaService $zatca): int
    {
        ['total' => $total, 'accepted' => $accepted] = $zatca->submitPending(retriableOnly: ! $this->option('all'));

        $this->info("Submitted {$total} invoice(s): {$accepted} accepted, ".($total - $accepted).' failed.');

        return $total === $accepted ? self::SUCCESS : self::FAILURE;
    }
}
