<?php

namespace Sofa\LaravelSortable;

trait Sortable
{
    /**
     * Whether to trigger reordering of other models during next save operation.
     *
     * @var boolean
     */
    protected $reorder = true;

    /**
     * Boot the trait for a model.
     *
     * @return void
     */
    protected static function bootSortable()
    {
        static::observe(Sorter::class, -999);
        static::addGlobalScope(new SortedQuery);
    }

    /**
     * Name of the column used for sorting.
     *
     * @return string
     */
    public static function sortableColumn()
    {
        return defined('static::SORTABLE_BY') ? static::SORTABLE_BY : 'sortable_position';
    }

    /**
     * Get max (last) sortable position value.
     *
     * @return integer
     */
    public static function maxPosition()
    {
        return static::max(static::sortableColumn());
    }

    /**
     * Get/set position attribute of this model.
     *
     * @param  integer $position  If provided, method works as setter
     * @return integer|null|$this
     */
    public function sortablePosition($position = null)
    {
        return func_num_args()
                ? $this->setAttribute($this->sortableColumn(), $position)
                : $this->getAttributeFromArray($this->sortableColumn());
    }

    /**
     * Move model to given position.
     *
     * @param  integer $position
     * @return $this
     */
    public function moveTo($position)
    {
        $this->sortablePosition($position)->save();

        return $this;
    }

    /**
     * Move model N steps up.
     *
     * @param  integer $steps
     * @return $this
     */
    public function moveUp($steps = 1)
    {
        return $this->move(-abs($steps));
    }


    /**
     * Move model to the top of the list.
     *
     * @return $this
     */
    public function moveToTop()
    {
        return $this->move('top');
    }

    /**
     * Move model N steps down.
     *
     * @param  integer $steps
     * @return $this
     */
    public function moveDown($steps = 1)
    {
        return $this->move(abs($steps));
    }

    /**
     * Move model to the end of the list.
     *
     * @param  integer $position
     * @return $this
     */
    public function moveToEnd()
    {
        return $this->move('end');
    }

    /**
     * Move model to the top/end of the list or N steps up/down.
     *
     * @param  string|integer $position
     * @return $this
     */
    protected function move($position)
    {
        $newPosition = $position == 'top'
                        ? 1
                        : ($position == 'end'
                            ? static::count($this->sortableColumn())
                            : $this->sortablePosition() + $position);

        $this->sortablePosition($newPosition)->save();

        return $this;
    }

    /**
     * Swap position with another model.
     *
     * @param  static|integer $other
     * @return $this
     */
    public function swapPosition($other)
    {
        if (!$other instanceof static) {
            $other = static::findAtPosition($other);
        }

        $myPosition = $this->sortablePosition();
        $otherPosition = $other->sortablePosition();

        $this->getConnection()
                ->transaction(function () use ($other, $myPosition, $otherPosition) {
                    $this->reordering(false)->sortablePosition($otherPosition)->save();
                    $other->reordering(false)->sortablePosition($myPosition)->save();
                });

        return $this;
    }

    /**
     * Accessor for sortable position attribute
     * @link https://laravel.com/docs/eloquent-mutators#accessors-and-mutators
     *
     * @return integer|null
     */
    public function getSortablePositionAttribute()
    {
        return $this->sortablePosition();
    }

    /**
     * Determine whether position of this model changed.
     *
     * @return boolean
     */
    public function positionChanged()
    {
        return $this->isDirty($this->sortableColumn());
    }

    /**
     * Get/Set reorder switch, which indicates, whether the next save operation
     * will trigger reordering of other models (it's disabled when swapping).
     *
     * @param  boolean $reorder
     * @return $this|boolean
     */
    public function reordering($reorder = null)
    {
        if (func_num_args() === 0) {
            return $this->reorder;
        }

        $this->reorder = $reorder;

        return $this;
    }
}
