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

    public function get(string $modelClass, ?int $branchId = null, bool $fallbackToMain = true): ?Model
    {
        $model = new $modelClass();
        $table = $model->getTable();
        if (!Schema::hasTable($table)) return null;

        if (!Schema::hasColumn($table, 'branch_id')) {
            return $modelClass::query()->first();
        }

        $branchId = $branchId ?: $this->mainBranchId();
        if (!$branchId) return null;

        $setting = $modelClass::query()->where('branch_id', $branchId)->first();
        if ($setting || !$fallbackToMain) {
            return $setting;
        }

        $mainBranchId = $this->mainBranchId();
        return $branchId !== $mainBranchId && $mainBranchId
            ? $modelClass::query()->where('branch_id', $mainBranchId)->first()
            : null;
    }
}
