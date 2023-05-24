<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contributor extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'employment_no',
        'started_at',
        'finished_at',
        'activity_type_id'
    ];

    protected $casts = [
        'person_id'        => 'integer',
        'activity_type_id' => 'integer',
        'started_at'       => 'datetime',
        'finished_at'      => 'datetime',
    ];


    /******************************************* Relations *******************************************/


    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }


}
