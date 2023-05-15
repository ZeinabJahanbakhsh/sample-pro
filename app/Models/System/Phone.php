<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Phone extends Model
{
    protected $fillable = [
        'phone_number'
    ];

    protected $casts = [
        'location_id' => 'integer',
    ];

    public function location(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }


}