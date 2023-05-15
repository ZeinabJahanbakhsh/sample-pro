<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Throwable;
use Exception;

class ApplicationException extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'message',
        'trace'
    ];

    protected static function boot()
    {
        static::saving(function ($model) {
            $model->ip_address = request()->ip();
            $model->user_id    = !auth()->guest() ? user()->id : null;
        });
        parent::boot();
    }

    public static function log(Throwable $throwable)
    {
        try {
            static::create([
                'code' =>  $throwable->getCode(),
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString()
            ]);
        } catch (Exception $exception) {
            //
        }
    }
}
