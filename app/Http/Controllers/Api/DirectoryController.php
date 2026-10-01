<?php

namespace App\Http\Controllers\Api;

use App\Directory\DoctorFilters;
use App\Directory\HospitalFilters;
use App\Directory\ProviderSearch;
use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\HospitalResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only view of the provider directory. It runs the same queries as the
 * agent's search tools, which makes it the reference for checking that what
 * the assistant showed is what the database holds.
 */
class DirectoryController extends Controller
{
    private const PAGE_SIZE = 50;

    public function doctors(Request $request, ProviderSearch $search): AnonymousResourceCollection
    {
        $filters = DoctorFilters::fromArray($request->validate(DoctorFilters::rules()));
        $result = $search->doctors($filters, self::PAGE_SIZE);

        return DoctorResource::collection($result->items)->additional(['meta' => ['total' => $result->total]]);
    }

    public function hospitals(Request $request, ProviderSearch $search): AnonymousResourceCollection
    {
        $filters = HospitalFilters::fromArray($request->validate(HospitalFilters::rules()));
        $result = $search->hospitals($filters, self::PAGE_SIZE);

        return HospitalResource::collection($result->items)->additional(['meta' => ['total' => $result->total]]);
    }
}
