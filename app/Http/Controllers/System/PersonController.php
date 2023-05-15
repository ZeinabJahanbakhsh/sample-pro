<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\Base\ActivityType;
use App\Models\Base\City;
use App\Models\Base\Department;
use App\Models\Base\Grade;
use App\Models\Base\Tag;
use App\Models\System\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Arr;

class PersonController extends Controller
{
    /**
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        [
            'page'     => $page,
            'per_page' => $perPage,
        ] = $request->all();

        $person = Person::with(['department', 'contributors'])->paginate(
            $perPage ?? 10,
            ['*'],
            'page',
            $page ?? 1
        );

        return PersonResource::collection($person);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function store(Request $request)
    {
        //$this->validationRequest($request);

        DB::transaction(function () use ($request) {

            //people
            $person = Person::forceCreate([
                'first_name'    => $request->input('first_name'),
                'last_name'     => $request->input('last_name'),
                'national_code' => $request->input('national_code'),
                'mobile'        => $request->input('mobile'),
                'email'         => $request->input('email'),
                'birthdate'     => $request->input('birthdate'),
                'department_id' => $request->input('department_id'),
                'grade_id'      => $request->input('grade_id'),
                'employment_no' => $request->input('employment_no')
            ]);

            //contributors
            //$person->contributors()->createMany($request->input('contributors'));
            $person->contributors()
                   ->createMany(
                       $request->collect('contributors')
                               ->map(fn($item) => Arr::only($item, [
                                   'first_name',
                                   'last_name',
                                   'employment_no',
                                   'started_at',
                                   'finished_at',
                                   'activity_type_id'
                               ]))
                   );

            /*$request->collect('contributors')
                    ->each(fn($contributor) => $person->contributors()->forceCreate([
                        'first_name'       => $contributor['first_name'],
                        'last_name'        => $contributor['last_name'],
                        'employment_no'    => $contributor['employment_no'],
                        'started_at'       => $contributor['started_at'],
                        'finished_at'      => $contributor['finished_at'],
                        'activity_type_id' => $contributor['activity_type_id'],
                    ]));*/

            //locations
            $request->collect('locations')
                    ->each(function ($data) use ($person) {
                        $location = $person->locations()->forceCreate(Arr::only($data, ['name', 'address']));
                        $location->phones()->createMany(Arr::map($data['phones'], fn($phone_number) => compact('phone_number')));
                    });

            //tags
            $tagIds = [];
            $request->collect('tags')
                    ->each(fn($tagName) => array_push($tagIds, Tag::firstOrCreate(['name' => $tagName])->id));

            $person->tags()->sync($tagIds);
        });

        return [
            'message' => __('store-success')
        ];

    }


    /**
     * @param Request $request
     * @param Person  $person
     * @return array
     */
    public function update(Request $request, Person $person)
    {
//        $request->merge([$person]);
        $this->validationRequest($request, $person);

        DB::transaction(function () use ($request, $person) {

            //people
            $person->update([
                'first_name'    => $request->input('first_name'),
                'last_name'     => $request->input('last_name'),
                'national_code' => $request->input('national_code'),
                'mobile'        => $request->input('mobile'),
                'email'         => $request->input('email'),
                'birthdate'     => $request->input('birthdate'),
                'department_id' => $request->input('department_id'),
                'grade_id'      => $request->input('grade_id'),
                'employment_no' => $request->input('employment_no')
            ]);

            //contributors
            $request->collect('contributors')
                    ->each(fn($contributor) => $person->contributors()->update([
                        'person_id'        => $person->id,
                        'first_name'       => $contributor['first_name'],
                        'last_name'        => $contributor['last_name'],
                        'employment_no'    => $contributor['employment_no'],
                        'started_at'       => $contributor['started_at'],
                        'finished_at'      => $contributor['finished_at'],
                        'activity_type_id' => $contributor['activity_type_id'],
                    ]));

            //locations
            $request->collect('locations')
                    ->each(fn($location) => $person->locations()->update([
                        //'city_id'   => $location['city_id'],
                        'name'    => $location['name'],
                        'address' => $location['address'],
                        //'phones'   => collect($location['phones']),
                    ]));

            //tags
            /* $request->collect('tags')
                 ->each(fn($tag) => Tag::create([
                     'name' => $tag['name'],
                 ]));*/

        });

        return [
            'message' => __('update-success')
        ];

    }


    public function destroy(Person $person)
    {

        DB::transaction(function () use ($person) {
            //$person->contributors()->delete();
            $person->locations()->delete();
            //$person->phones()->delete();
            //$person->delete();
        });

        return [
            'message' => __('destroy-success')
        ];

    }

    private function validationRequest(Request $request, Person $person = null)
    {
//        $rules =
//            [
//
//            ];

//        //update
//        if (!empty($request->route()->parameters())) {
//            $rules = [
//                'email' => 'email|unique:person,email,' . $request->person->id,
//                'first_name' => ['max:50', 'persian_alpha'],
//                'last_name' => ['max:50', 'persian_alpha'],
//                'national_code' => ['ir_national_code'],
//                'contributors.*' => ['sometimes', 'required'],
//                'contributors.*.first_name' => ['max:50', 'persian_alpha'],
//                'contributors.*.last_name' => ['max:50', 'persian_alpha'],
//                ...$rules,
//            ];
//        } //store
//        else {
//            $rules = [
//                'email' => ['email', Rule::unique('people', 'email')],
//                ...$rules,
//            ];
//        }


        $this->validate($request, [
            'email'                           => ['required', 'email', Rule::unique(Person::class)->ignoreModel($person)],
            'first_name'                      => ['required', 'max:50', 'persian_alpha'],
            'last_name'                       => ['required', 'max:50', 'persian_alpha'],
            'national_code'                   => ['required', 'ir_national_code'],
            'mobile'                          => 'nullable|ir_mobile',
            'birthdate'                       => ['nullable', 'date', 'before:now'],
            'department_id'                   => ['nullable', Rule::modelExists(Department::class)],
            'grade_id'                        => ['nullable', Rule::modelExists(Grade::class)],
            'employment_no'                   => ['nullable'],
            'contributors.*'                  => ['sometimes', 'required'],
            'contributors.*.first_name'       => ['required', 'max:50', 'persian_alpha'],
            'contributors.*.last_name'        => ['required', 'max:50', 'persian_alpha'],
            'contributors.*.employment_no'    => ['nullable', 'integer'],
            'contributors.*.started_at'       => ['nullable', 'date'],
            'contributors.*.finished_at'      => ['nullable', 'date'],
            'contributors.*.activity_type_id' => ['nullable', Rule::modelExists(ActivityType::class)],
            'locations.*'                     => ['sometimes', 'required'],
            'locations.*.city_id'             => ['nullable', Rule::modelExists(City::class)],
            'locations.*.name'                => ['nullable', 'persian_alpha'],
            'locations.*.address'             => ['nullable', 'persian_alpha'],
            'locations.*.phones.*'            => ['nullable'],
            'tags'                            => ['sometimes', 'required', Rule::modelExists(Tag::class)]
        ]);

    }

}