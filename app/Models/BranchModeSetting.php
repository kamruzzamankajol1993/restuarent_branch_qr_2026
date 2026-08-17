<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchModeSetting extends Model
{
    use HasFactory;

    public const MODE_SINGLE = 'single';
    public const MODE_MULTIPLE = 'multiple';

    protected $guarded = [];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['mode' => self::MODE_MULTIPLE, 'multi_branch_activated_at' => now()]
        );
    }
}
