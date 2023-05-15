<?php

namespace App\Models\System;

use EloquentTraits\Expirable\Expirable;
use EloquentTraits\HasPersianCharacters;
use EloquentTraits\HasQuickFilter;
use EloquentTraits\HasSorting;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{

    protected       $fillable           = ['name'];
    protected array $quickFilterColumns = ['name'];
    protected array $persian_columns    = ['name'];
}
