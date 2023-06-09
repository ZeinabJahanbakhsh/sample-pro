<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'address' , 'name'
    ];

    protected $casts = [
        'person_id' => 'integer',
    ];


    /******************************************* Relations *******************************************/


    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(Phone::class);
    }


}
