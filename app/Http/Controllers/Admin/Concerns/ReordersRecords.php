<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ReordersRecords
{
    /**
     * Move a record one position up or down within the list the admin is
     * looking at.
     *
     * Looking the neighbour up with `where('sort_order', '<', $current)` breaks
     * as soon as two rows share a value — and the column defaults to 0, so
     * seeded rows all tie and the button did nothing. Normalising the whole
     * table to distinct, gap-free positions first makes every click move the
     * record exactly one visible row.
     *
     * @param  Builder<covariant Model>  $all      every record, in the canonical order
     * @param  Builder<covariant Model>  $visible  the rows the admin can see, same order
     */
    protected function moveRecord(
        Builder $all,
        Builder $visible,
        Model $record,
        string $direction,
        string $column = 'sort_order'
    ): bool {
        $ordered = $all->get()->values();

        foreach ($ordered as $position => $row) {
            $expected = $position + 1;
            if ((int) $row->{$column} !== $expected) {
                $row->{$column} = $expected;
                $row->save();
            }
        }

        $rows = $visible->get()->values();
        $index = $rows->search(fn (Model $row) => $row->getKey() === $record->getKey());

        if ($index === false) {
            return false;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($targetIndex < 0 || $targetIndex >= $rows->count()) {
            return false;
        }

        $current = $rows[$index];
        $target = $rows[$targetIndex];

        [$current->{$column}, $target->{$column}] = [$target->{$column}, $current->{$column}];
        $current->save();
        $target->save();

        return true;
    }
}
