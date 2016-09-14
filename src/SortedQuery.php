<?php

namespace Sofa\LaravelSortable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ScopeInterface;

class SortedQuery implements ScopeInterface
{
    /**
     * By default Sortable results will be sorted ascending.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function apply(Builder $query, Model $model)
    {
        $this->extend($query);
    }

    /**
     * Extend query builder with handy macros.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function extend(Builder $query)
    {
        $column = $query->getModel()->sortableColumn();

        $query->macro('sorted', function ($query, $dir = 'asc') use ($column) {
            return $query->orderBy($column, ($dir == 'desc') ? 'desc' : 'asc');
        });

        $query->macro('reversed', function ($query) {
            return $query->sorted('desc');
        });

        $query->macro('findAtPosition', function ($query, $position, $cols = ['*']) use ($column) {
                return $query->atPosition($position)->first($cols);
            }
        );

        $query->macro('atPosition', function ($query, $position) use ($column) {
            return $query->where($column, $position);
        });
    }

    public function remove(Builder $query, Model $model)
    {
        //
    }
}
