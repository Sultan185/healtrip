<?php

namespace App\Directory;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
final readonly class SearchResult
{
    /**
     * @param  Collection<int, TModel>  $items  The top matches, capped by the search limit.
     * @param  int  $total  How many rows matched before the cap.
     */
    public function __construct(
        public Collection $items,
        public int $total,
    ) {}
}
