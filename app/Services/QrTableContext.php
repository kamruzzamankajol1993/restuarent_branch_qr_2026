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
        if (Schema::hasColumn('tables', 'qr_token')) {
            $table = Table::query()->where('qr_token', $reference)->first();
            if ($table) {
                return $this->ensureActiveBranch($table);
            }
        }

        $decoded = base64_decode($reference, true);
        if ($decoded !== false && ctype_digit((string) $decoded)) {
            $table = Table::query()->find((int) $decoded);
            if ($table) {
                return $this->ensureActiveBranch($table);
            }
        }

        throw new NotFoundHttpException('Invalid or expired table QR code.');
    }

    private function ensureActiveBranch(Table $table): Table
    {
        if (Schema::hasColumn('tables', 'branch_id')) {
            if (!$table->branch_id) {
                // Full multi-branch QR conversion is fail-closed. A table without branch
                // ownership must never fall back to a global menu/settings query.
                throw new NotFoundHttpException('This table is not assigned to an active branch.');
            }

            $active = Branch::query()->whereKey($table->branch_id)->where('status', true)->exists();
            if (!$active) {
                throw new NotFoundHttpException('This branch is currently unavailable.');
            }
        }

        return $table;
    }
}
