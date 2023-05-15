<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Department;
use App\Models\System\DepartmentCategory;
use App\Models\System\DepartmentType;
use BasicCrud\Http\Actions\HasExpireAction;
use BasicCrud\Http\Actions\HasStoreAction;
use BasicCrud\Http\Actions\HasUpdateAction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentsController extends Controller
{
    use HasStoreAction;
    use HasUpdateAction;
    use HasExpireAction;

    public $model = Department::class;

    /**
     * @return mixed
     */
    public function index()
    {
        $getMappedArray = function ($department) use (&$getMappedArray) {
            /** @var $department Department */
            return [
                'id'                       => $department->id,
                'parent_id'                => $department->parent_id,
                'left_id'                  => $department->left_id,
                'right_id'                 => $department->right_id,
                'name'                     => $department->name,
                'display_name'             => $department->display_name,
                'department_number'        => $department->department_number,
                'department_type_name'     => $department->departmentType?->name,
                'department_type_id'       => $department->department_type_id,
                'department_category_id'   => $department->department_category_id,
                'department_category_name' => $department->departmentCategory?->name,
                'created_at'               => $department->created_at,
                'updated_at'               => $department->updated_at,
                'expired_at'               => $department->expired_at,
                'is_expired'               => $department->is_expired,
                'is_readonly'              => $department->is_readonly,
                'children'                 => $department->children->map(fn($child) => $getMappedArray($child))
            ];
        };

        return Department::defaultOrder()
                         ->with([
                             'departmentType',
                             'departmentType.supervisorGrade',
                             'departmentCategory'
                         ])
                         ->get()
                         ->toTree()
                         ->map(fn($department) => $getMappedArray($department));
    }

    /**
     * @return array
     */
    public function list()
    {
        $nodes  = Department::withDepth()
                            ->with(['departmentType', 'departmentType.supervisorGrade'])
                            ->get()
                            ->toTree();
        $output = [];

        $traverse = function ($departments, $prefix = ' - ') use (&$traverse, &$output) {
            foreach ($departments as $department) {
                /** @var $department \App\Models\System\Department */
                $output[] = [
                    'id'   => $department->id,
                    'name' => $prefix . ' ' . "{$department->departmentType->name} {$department->name}"
                ];

                $traverse($department->children, $prefix . ' - ');
            }
        };

        $traverse($nodes);

        return $output;
    }

    public function updateRoot(Request $request, Department $department)
    {
        abort_if(
            !$department->isRoot(),
            403,
            __('messages.operation-failed')
        );

        $department->fill($request->only([
            'name',
            'department_number',
            'department_category_id',
            'department_type_id'
        ]));
        $department->save();

        return [
            'message' => __('messages.update-success')
        ];
    }

    /**
     * @param \App\Models\System\Department $department
     * @return array
     */
    public function moveUp(Department $department)
    {
        $moved = $department->up();
        return compact('moved');
    }

    /**
     * @param \App\Models\System\Department $department
     * @return array
     */
    public function moveDown(Department $department)
    {
        $moved = $department->down();
        return compact('moved');
    }

    /**
     * @param \Illuminate\Http\Request      $request
     * @param \App\Models\System\Department $department
     * @return void
     */
    public function moveFirst(Request $request, Department $department)
    {

    }

    /**
     * @param \Illuminate\Http\Request      $request
     * @param \App\Models\System\Department $department
     * @return void
     */
    public function moveLast(Request $request, Department $department)
    {
    }

    /**
     * @param \Illuminate\Http\Request      $request
     * @param \App\Models\System\Department $model
     * @return array[]
     */
    public function getRules(Request $request, Department $model): array
    {
        return [
            'name'                   => [
                'required',
                'string',
                Rule::unique((new Department())->getTable(), 'name')
                    ->ignore($model->id)
            ],
            'department_number'      => [
                'required',
                'integer',
                Rule::unique((new Department())->getTable(), 'department_number')
                    ->ignore($model->id)
            ],
            'department_type_id'     => [
                'required',
                'integer',
                Rule::exists((new DepartmentType())->getTable(), 'id')
            ],
            'department_category_id' => [
                'required',
                'integer',
                Rule::exists((new DepartmentCategory())->getTable(), 'id')
            ],
            'parent_id'              => [
                'required',
                'integer',
                Rule::exists((new Department())->getTable(), 'id')
            ],
            'expired_at'             => 'nullable|date'
        ];
    }
}
