<?php

namespace App\Console\Commands;

use App\Domain\Shared\AuditRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyAuditChains extends Command
{
    protected $signature = 'security:verify-audit-chains';

    protected $description = 'Verify append-only module audit event hashes and chain heads.';

    public function handle(AuditRecorder $audit): int
    {
        $valid = true;
        $modules = DB::table('module_audit_events')->distinct()->orderBy('module')->pluck('module');
        foreach ($modules as $module) {
            $previous = null;
            $lastId = null;
            foreach (DB::table('module_audit_events')->where('module', $module)->orderBy('id')->get() as $row) {
                if ($row->previous_hash !== $previous || ! hash_equals($row->event_hash, $audit->hash((array) $row))) {
                    $this->error("Audit chain integrity failure in {$module} at event {$row->id}.");
                    $valid = false;
                    break;
                }
                $previous = $row->event_hash;
                $lastId = $row->id;
            }
            $head = DB::table('module_audit_chain_heads')->where('module', $module)->first();
            if (! $head || (int) $head->last_event_id !== (int) $lastId || ! hash_equals((string) $head->last_hash, (string) $previous)) {
                $this->error("Audit chain head mismatch in {$module}.");
                $valid = false;
            }
        }
        if (! $valid) {
            return self::FAILURE;
        }
        $this->info('Audit chains verified for '.$modules->count().' module(s).');

        return self::SUCCESS;
    }
}
