<?php

namespace Sofa\History\Tests;

class DummyUnsupportedRelationModel extends User
{
    protected $table = 'users';

    public function unsupportedRelation()
    {
        return new DummyUnsupportedRelation($this->newQuery(), $this);
    }

    public function hasOneWithUnsupportedWhere()
    {
        return $this
            ->hasOne(User::class)
            ->orderBy('id')
            ->whereIn('id', [1, 2, 3])
            ->whereNotIn('id', [4, 5, 6])
            ->where('id', '>', 0)
            ->whereRaw('raw query');
    }

    public function hasOneWithoutOrdering()
    {
        return $this->hasOne(User::class);
    }
}
