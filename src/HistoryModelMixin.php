<?php

namespace Sofa\History;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @see History::recreate()
 * @see HistoryServiceProvider::registerHistoryMacro()
 * @method static static recreate(string|int $id, string|\DateTimeInterface $timestamp, array|Arrayable $relations = [])
 */
trait HistoryModelMixin
{
}
