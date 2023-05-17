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
use Illuminate\Support\Collection;
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
        $this->validationRequest($request);

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
                    ->each(function ($tag) use ($person, &$tagIds) {
                        $tagIds[] = Tag::firstOrCreate([
                            'name' => $tag
                        ])->id;
                        $person->tags()->sync($tagIds);
                    });

//            $tagIds = [];
//            $request->collect('tags')
//                    ->each(fn($tagName) => array_push($tagIds, Tag::firstOrCreate(['name' => $tagName])->id));
//
//            //$person->tags()->sync($tagIds);
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
            //*maybe delete all recorde next create!
            $inputContributors = $request->collect('contributors');
            $personContributors = $person->contributors();

            //pre-records->delete
            $personContributors->whereNotIn('id', $inputContributors->filter(fn($item) => isset($item['id']))
                                                                        ->pluck('id')
                                                                        ->toArray()
                                            )->delete();

            //new->add
            $inputContributors->filter(fn(array $input) => !isset($input['id']))
                              ->whenNotEmpty(fn(Collection $items) => $personContributors->createMany(
                                      $items->map(fn(array $item) => Arr::only($item, [
                                              'first_name',
                                              'last_name',
                                              'employment_no',
                                              'started_at',
                                              'finished_at',
                                              'activity_type_id',
                                          ]))->toArray()
                                  )
                              );

            //again send->update
            $inputContributors->filter(fn($input) => isset($input['id']))
                              ->each(fn(array $input) => $personContributors->where('id', $input['id'])
                                                                    ->update(Arr::only($input, [
                                                                    'first_name',
                                                                    'last_name',
                                                                    'employment_no',
                                                                    'started_at',
                                                                    'finished_at',
                                                                    'activity_type_id',
                                                                ]))
                              );

            //locations
            $inputLocations  = $request->collect('locations');
            $personLocations = $person->locations();

            //we have id -> new records -> delete pre-records that are not in db
            $personLocations->whereNotIn('id', $inputLocations->filter(fn($item) => isset($item['id']))
                                                                   ->pluck('id')
                                                                   ->toArray()
                                        )->delete();

           /*$inputLocations->filter(fn(array $input) => !isset($input['id']))
                                      ->whenNotEmpty(fn(Collection $items) => $personLocations
                                          ->createMany(
                                              $items->map(fn(array $item) => Arr::only($item,
                                                  [
                                                      'name',
                                                      'address',
                                                  ]
                                              ))->toArray()
                                          )
                                      );*/

            $inputLocations->each(function ($data) use ($person) {
                        $location = $person->locations()->firstOrCreate([
                            'name'    => $data['name'],
                            'address' => $data['address']
                        ]);

                        //Can I delete all records next add new records?

                        $location->phones()->createMany(Arr::map($data['phones'], fn($phone_number) => compact('phone_number')));

                        /*$location->phones()->firstOrCreate([
                            //'phone_number' => Arr::map($data['phones'], fn($phone_number) => $phone_number)
                            //'phone_number' =>  Arr::map($data['phones'],fn($phone_number) => compact('phone_number'))
                            'phone_number' => Arr::map($data['phones'], fn($phone_number) => compact('phone_number'))
                            //'phone_number' => Arr::map($data['phones'], fn($phone_number) => compact('phone_number'))
                        ]);*/
                    });

            $inputLocations->filter(fn($input) => isset($input['id']))
                              ->each(fn(array $input) => $personLocations->where('id', $input['id'])
                                  ->update(Arr::only($input, [
                                      'name',
                                      'address',
                                  ]))
                              );

                        //Can I delete all records next add new records?
                       /* $location->phones()->delete();
                        $location->phones()->createMany(Arr::map($data['phones'], fn($phone_number) => compact('phone_number')));*/

            //tags
            $tagIds = [];
            $request->collect('tags')
                    ->each(function ($tag) use ($person, &$tagIds) {
                        $tagIds[] = Tag::firstOrCreate([
                            'name' => $tag
                        ])->id;

                        $person->tags()->sync($tagIds);
                    });
        });

        return [
            'message' => __('update-success')
        ];

    }


    /**
     * @param Person $person
     * @return array
     */
    public function destroy(Person $person)
    {
        DB::transaction(function () use ($person) {
            $person->contributors()->delete();
            $person->phones()->delete();
            $person->locations()->delete();
            $person->tags()->sync([]);
            $person->delete();
        });

        return [
            'message' => __('destroy-success')
        ];

    }

    /**
     * @param Request     $request
     * @param Person|null $person
     * @return void
     */
    private function validationRequest(Request $request, Person $person = null)
    {
        //$request->route()->parameters()
        //$request->method()

        $this->validate($request, [
            'email'                           => ['required', 'email', Rule::unique('people', 'email')->ignore($person?->id)],
            'first_name'                      => ['required', 'max:50', 'persian_alpha'],
            'last_name'                       => ['required', 'max:50', 'persian_alpha'],
            'national_code'                   => ['required', 'ir_national_code'],
            'mobile'                          => 'nullable|ir_mobile',
            'birthdate'                       => ['nullable', 'date', 'before:now'],
            'department_id'                   => ['nullable', Rule::modelExists(Department::class)],
            'grade_id'                        => ['nullable', Rule::modelExists(Grade::class)],
            'employment_no'                   => 'nullable',
            'contributors.*'                  => 'required',
            'contributors.*.first_name'       => ['required', 'max:50', 'persian_alpha'],
            'contributors.*.last_name'        => ['required', 'max:50', 'persian_alpha'],
            'contributors.*.employment_no'    => 'nullable', 'integer',
            'contributors.*.started_at'       => 'nullable', 'date',
            'contributors.*.finished_at'      => 'nullable', 'date',
            'contributors.*.activity_type_id' => ['nullable', Rule::modelExists(ActivityType::class)],
            'locations.*'                     => 'nullable',
            'locations.*.city_id'             => ['nullable', Rule::modelExists(City::class)],
            'locations.*.name'                => 'nullable', 'persian_alpha',
            'locations.*.address'             => 'nullable', 'persian_alpha',
            'locations.*.phones.*'            => 'nullable',
            'tags'                            => ['required']
        ]);

    }

}
