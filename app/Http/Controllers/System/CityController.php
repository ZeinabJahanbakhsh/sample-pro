<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Base\City;
use BasicCrud\Http\Actions\HasSearchAction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CityController extends Controller
{
    use HasSearchAction;

    public $model = City::class;

    public function searchQuery(Request $request, string $keyword): Collection
    {
        return City::withExpired()
                   ->with('province')
                   ->whereHas('province', fn($builder) => $builder->whereHas('country', fn($sub) => $sub->homeCountry()))
                   ->where(fn($builder) => (
                   $builder
                       ->where('name', 'like', "%{$keyword}%")
                       ->orWhereHas('province', fn($builder) => $builder->where('name', 'like', "%{$keyword}%"))
                   ))
                   ->orderBy('name')
                   ->get()
                   ->map(fn($city) => [
                       'id'   => $city->id,
                       'name' => "{$city->province->name} - {$city->name}"
                   ]);
    }
}
