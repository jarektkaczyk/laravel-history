<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class DummyUnsupportedRelation extends Relation
{
    public function addConstraints() { }

    public function addEagerConstraints(array $models) { }

    public function initRelation(array $models, $relation) { }

    public function match(array $models, Collection $results, $relation) { }

    public function getResults() { }
}
