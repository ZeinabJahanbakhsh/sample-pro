<?php

namespace App\Models\System;

use App\Models\Base\Grade;
use App\Models\Base\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Person extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'national_code',
        'mobile',
        'email',
        'birthdate',
        'employment_no',
        //'department_id', 'grade_id'
    ];

    protected $casts = [
        'department_id' => 'integer',
        'grade_id'      => 'integer',
    ];


    public function department(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(Contributor::class);
    }

    public function phones(): HasManyThrough
    {
        return $this->hasManyThrough(Phone::class, Location::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'person_tag');
    }

}
