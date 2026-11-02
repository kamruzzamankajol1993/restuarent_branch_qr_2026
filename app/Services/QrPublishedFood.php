<?php

namespace App\Services;

use App\Models\FoodItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** Single, fail-closed QR food policy shared by listing, cart and checkout. */
class QrPublishedFood
{
    private ?bool $hasPublicationColumn = null;

    public function forBranch(int $branchId): Builder
    {
        return $this->apply(FoodItem::query(), $branchId);
    }

    public function apply(Builder $query, int $branchId): Builder
    {
        $query->where('food_items.branch_id', $branchId)
            ->where('food_items.is_available', 1)
            ->where('food_items.is_draft', 0)
            ->where('food_items.is_dine_in', 1);

        // Do not reveal potentially unpublished shared food on an incomplete schema.
        $this->hasPublicationColumn ??= Schema::hasColumn('food_items', 'shared_menu_unpublished_at');
        if (!$this->hasPublicationColumn) {
            return $query->whereRaw('1 = 0');
        }
        $query->whereNull('food_items.shared_menu_unpublished_at');

        // Respect the configured daily availability, including windows crossing midnight.
        // Empty/null active_days means "all days" (the legacy default).
        $now = now();
        $clock = $now->format('H:i:s');
        $day = $now->format('D');
        $previousDay = $now->copy()->subDay()->format('D');

        $query->where(function (Builder $q) use ($clock) {
            $q->where(function (Builder $q) {
                $q->whereNull('food_items.start_time')->whereNull('food_items.end_time');
            })->orWhere(function (Builder $q) use ($clock) {
                $q->whereNull('food_items.start_time')->whereTime('food_items.end_time', '>=', $clock);
            })->orWhere(function (Builder $q) use ($clock) {
                $q->whereNull('food_items.end_time')->whereTime('food_items.start_time', '<=', $clock);
            })->orWhere(function (Builder $q) use ($clock) {
                $q->whereColumn('food_items.start_time', '<=', 'food_items.end_time')
                    ->whereTime('food_items.start_time', '<=', $clock)
                    ->whereTime('food_items.end_time', '>=', $clock);
            })->orWhere(function (Builder $q) use ($clock) {
                $q->whereColumn('food_items.start_time', '>', 'food_items.end_time')
                    ->where(function (Builder $window) use ($clock) {
                        $window->whereTime('food_items.start_time', '<=', $clock)
                            ->orWhereTime('food_items.end_time', '>=', $clock);
                    });
            });
        });

        $query->where(function (Builder $q) use ($clock, $day, $previousDay) {
            $q->whereNull('food_items.active_days')
                ->orWhereRaw('JSON_LENGTH(food_items.active_days) = 0')
                ->orWhereRaw(
                    'JSON_CONTAINS(food_items.active_days, JSON_QUOTE(CASE '
                    . 'WHEN food_items.start_time IS NOT NULL AND food_items.end_time IS NOT NULL '
                    . 'AND food_items.start_time > food_items.end_time AND ? <= food_items.end_time '
                    . 'THEN ? ELSE ? END))',
                    [$clock, $previousDay, $day]
                );
        });

        return $query;
    }
}
