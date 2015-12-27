<?php
namespace Atlas\Orm\Table;

use Atlas\Orm\Exception;

class Row
{
    // new instance, in memory only
    const IS_NEW = 'IS_NEW';

    // selected, and not yet modified in memory
    const IS_CLEAN = 'IS_CLEAN';

    // selected/inserted/updated, then modified in memory
    const IS_DIRTY = 'IS_DIRTY';

    // marked for deletion but not deleted, modification in memory allowed
    const IS_TRASH = 'IS_TRASH';

    // inserted, and not again modified in memory
    const IS_INSERTED = 'IS_INSERTED';

    // updated, and not again modified in memory
    const IS_UPDATED = 'IS_UPDATED';

    // deleted, modification in memory not allowed
    const IS_DELETED = 'IS_DELETED';

    private $tableClass;

    private $primary;

    private $cols = [];

    private $status;

    public function __construct($tableClass, Primary $primary, array $cols)
    {
        $this->tableClass = $tableClass;
        $this->primary = $primary;
        $this->cols = $cols;
        $this->status = static::IS_NEW;
    }

    public function __get($col)
    {
        $this->assertHas($col);

        if ($this->primary->has($col)) {
            return $this->primary->$col;
        }

        return $this->cols[$col];
    }

    public function __set($col, $val)
    {
        $this->assertHas($col);

        if ($this->primary->has($col)) {
            $this->primary->$col = $val;
            return;
        }

        $this->modify($col, $val);
    }

    public function __isset($col)
    {
        $this->assertHas($col);

        if ($this->primary->has($col)) {
            return isset($this->primary->$col);
        }

        return isset($this->cols[$col]);
    }

    public function __unset($col)
    {
        $this->assertHas($col);

        if ($this->primary->has($col)) {
            unset($this->primary->$col);
            return;
        }

        $this->modify($col, null);
    }

    protected function assertHas($col)
    {
        if (! $this->has($col)) {
            throw Exception::propertyDoesNotExist($this, $col);
        }
    }

    public function getTableClass()
    {
        return $this->tableClass;
    }

    public function assertTableClass($tableClass)
    {
        if ($tableClass !== $this->tableClass) {
            throw Exception::wrongTableClass($tableClass, $this->tableClass);
        }
    }

    public function has($col)
    {
        return array_key_exists($col, $this->cols)
            || $this->primary->has($col);
    }

    public function getArrayCopy()
    {
        return array_merge(
            $this->primary->getKey(),
            $this->cols
        );
    }

    /** @todo array_key_exists($col, $init) */
    public function getArrayDiff(array $init)
    {
        $diff = $this->getArrayCopy();
        foreach ($diff as $col => $val) {
            if ($this->isSameValue($init[$col], $diff[$col])) {
                unset($diff[$col]);
            }
        }
        return $diff;
    }

    public function getPrimary()
    {
        return $this->primary;
    }

    protected function modify($col, $new)
    {
        if ($this->isDeleted()) {
            throw Exception::immutableOnceDeleted($this, $col);
        }

        if ($this->isNew() || $this->isTrash()) {
            $this->cols[$col] = $new;
            return;
        }

        $old = $this->cols[$col];
        $this->cols[$col] = $new;
        if (! $this->isSameValue($old, $new)) {
            $this->status = static::IS_DIRTY;
        }
    }

    protected function isSameValue($old, $new)
    {
        return (is_numeric($old) && is_numeric($new))
            ? $old == $new // numeric, compare loosely
            : $old === $new; // not numeric, compare strictly
    }

    public function isNew()
    {
        return $this->status == static::IS_NEW;
    }

    public function isClean()
    {
        return $this->status == static::IS_CLEAN;
    }

    public function isDirty()
    {
        return $this->status == static::IS_DIRTY;
    }

    public function isTrash()
    {
        return $this->status == static::IS_TRASH;
    }

    public function isSaved() // persisted? flushed?
    {
        return $this->status == static::IS_INSERTED
            || $this->status == static::IS_UPDATED
            || $this->status == static::IS_DELETED;
    }

    public function isInserted()
    {
        return $this->status == static::IS_INSERTED;
    }

    public function isUpdated()
    {
        return $this->status == static::IS_UPDATED;
    }

    public function isDeleted()
    {
        return $this->status == static::IS_DELETED;
    }

    public function hasStatus($status)
    {
        return in_array($this->status, (array) $status);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        if (! defined("static::{$status}")) {
            throw Exception::invalidStatus($status);
        }
        $this->status = $status;
    }
}