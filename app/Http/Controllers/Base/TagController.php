<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Models\Base\Tag;
use BasicCrud\Http\Actions\HasIndexAction;
use BasicCrud\Http\Actions\HasListAction;
use BasicCrud\Http\Actions\HasStoreAction;
use BasicCrud\Http\Actions\HasUpdateAction;


class TagController extends Controller
{
    use HasIndexAction;
    use HasStoreAction;
    use HasUpdateAction;
    use HasListAction;

    public $model = Tag::class;

}
