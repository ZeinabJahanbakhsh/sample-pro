<?php

namespace BalajiDharma\LaravelAdminCore\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;

class UserController extends Controller
{
    public function helloWorld(/*$timezone*/): string
    {
        //return "Hello World";

      return  Carbon::now()->toDayDateTimeString();

        //return view('hello', ['name' => 'Samantha']);
        //return Carbon::now()->toDayDateTimeString();
        //return Carbon::now()->toDateTimeString();
        //return Carbon::now($timezone)->toDateTimeString();
    }
}
