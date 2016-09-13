<?php

namespace Sofa\Sortable;

use Illuminate\Database\Eloquent\Model;

class Sorter
{
    /**
     * Put newly created model at the end of the list.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function saving(Model $model)
    {
        if (is_null($position = $model->sortablePosition())) {
            $model->sortablePosition(1 + $model->maxPosition());
            return;
        }

        if ($model->reordering()) {
            // Make sure new position is valid, that is between min and max values.
            $model->sortablePosition(min($model->count(), max(1, $position)));
        }
    }

    /**
     * Reorder items if necessary.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function saved(Model $model)
    {
        // nothing changed or we're just swapping positions - do nothing
        if (!$model->reordering() || !$model->positionChanged()) {
            $model->reordering(true);
            return;
        }

        $to = $model->sortablePosition();
        $from = $model->getOriginal($model->sortableColumn());

        return $to < $from
                ? $this->movedUp($model, $to, $from)
                : $this->movedDown($model, $to, $from);
    }

    /**
     * Reorder items if necessary.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function deleted(Model $model)
    {
        $this->movedDown($model, $model->maxPosition(), $model->getOriginal($model->sortableColumn()));
    }

    /**
     * Put restored model at its previous position and reorder others appropriately.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function restored(Model $model)
    {
        $this->movedUp($model, $model->sortablePosition(), $model->maxPosition());
    }

    protected function movedUp($model, $newPosition, $oldPosition)
    {
        $model->getConnection()->transaction(function () use ($model, $newPosition, $oldPosition) {
            $model->newQuery()
                ->lockForUpdate()
                ->whereBetween($model->sortableColumn(), [$newPosition, $oldPosition])
                ->where($model->getKeyName(), '!=', $model->getKey())
                ->sorted()
                ->get()
                ->each(function ($other) use (&$newPosition) {
                    $other->reordering(false)->sortablePosition(++$newPosition)->save();
                });
        });
    }

    protected function movedDown($model, $newPosition, $oldPosition)
    {
        $model->getConnection()->transaction(function () use ($model, $newPosition, $oldPosition) {
            $model->newQuery()
                ->lockForUpdate()
                ->whereBetween($model->sortableColumn(), [$oldPosition, $newPosition])
                ->where($model->getKeyName(), '!=', $model->getKey())
                ->sorted()
                ->get()
                ->each(function ($other) use (&$oldPosition) {
                    $other->reordering(false)->sortablePosition($oldPosition++)->save();
                });
        });
    }
}
