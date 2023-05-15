<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Base\Grade;
use BasicCrud\Http\Actions\HasExpireAction;
use BasicCrud\Http\Actions\HasIndexAction;
use BasicCrud\Http\Actions\HasListAction;
use BasicCrud\Http\Actions\HasStoreAction;
use BasicCrud\Http\Actions\HasUpdateAction;

class GradeController extends Controller
{
    use HasIndexAction;
    use HasStoreAction;
    use HasUpdateAction;
    use HasExpireAction;
    use HasListAction;

    public $model = Grade::class;
}
