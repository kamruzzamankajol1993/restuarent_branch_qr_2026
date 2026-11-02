<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Table;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QrTableContext
{
    public function resolve(string $reference): Table
    {
        if (!Schema::hasTable('tables') || !Schema::hasTable('branches')
            || !Schema::hasColumn('tables', 'branch_id') || !Schema::hasColumn('tables', 'qr_token')) {
            throw new NotFoundHttpException('QR branch setup is incomplete. Contact the restaurant.');
        }
        // A signed-length random table token, not a guessable numeric table ID, is the QR identity.
        $table = Table::query()->where('qr_token', $reference)->first();
        if (!$table && config('qr.allow_legacy_table_ids', false)) {
            // Temporary transition ONLY for already printed old QR codes. Disable after reprinting.
            $decoded = base64_decode($reference, true);
            if ($decoded !== false && ctype_digit((string) $decoded)) {
                $table = Table::query()->find((int) $decoded);
            }
        }
        if (!$table || !$table->branch_id || !$table->qr_token) {
            throw new NotFoundHttpException('Invalid or expired table QR code.');
        }
        if (!Branch::query()->whereKey($table->branch_id)->where('status', 1)->exists()) {
            throw new NotFoundHttpException('This branch is currently unavailable.');
        }
        return $table;
    }
}
