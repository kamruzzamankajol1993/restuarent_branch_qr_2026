<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class QrBranchSettingResolver
{
    public function mainBranchId(): ?int
    {
        if (!Schema::hasTable('branches')) return null;
        return (int) (Branch::query()->where('is_main', true)->value('id') ?: Branch::query()->min('id')) ?: null;
    }

    /** All restaurant/tax/invoice/POS settings are shared across branches.
     * Legacy branch-specific rows are never used for QR calculations.
     */
    public function get(string $modelClass, ?int $branchId = null, bool $fallbackToMain = true): ?Model
    {
        $model = new $modelClass();
        $table = $model->getTable();
        if (!Schema::hasTable($table)) return null;

        if (!Schema::hasColumn($table, 'branch_id')) {
            return $modelClass::query()->orderBy('id')->first();
        }
        $main = $this->mainBranchId();
        return $main ? $modelClass::query()->where('branch_id', $main)->orderBy('id')->first() : null;
    }
}
