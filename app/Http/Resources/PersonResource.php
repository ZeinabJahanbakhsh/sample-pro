<?php

namespace App\Http\Resources;

use App\Models\System\Person;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @mixin Person
 */
class PersonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        return [
            'id'                => $this->id,
            'first_name'        => $this->first_name,
            'last_name'         => $this->last_name,
            'national_code'     => $this->national_code,
            'contributors_count' => count($this->contributors),
            'locations_count'   => count($this->locations),
            //'tag_names' => $this->tags()
        ];
    }

}
