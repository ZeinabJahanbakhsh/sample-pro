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
        $this->validationRequest($request);

        DB::transaction(function () use ($request) {

            $person = Person::forceCreate([
                'first_name'    => $request->input('first_name'),
                'last_name'     => $request->input('last_name'),
                'national_code' => $request->input('national_code'),
                'mobile'        => $request->input('mobile'),
                'email'         => $request->input('email'),
                'birthdate'     => $request->input('birthdate'),
                'employment_no' => $request->input('employment_no'),
                'department_id' => $request->input('department_id'),
                'grade_id'      => $request->input('grade_id'),
            ]);

            $person->contributors()->createMany(
                $request->collect('contributors')->map(fn($item) => Arr::only($item,
                    [
                        'first_name',
                        'last_name',
                        'employment_no',
                        'started_at',
                        'finished_at',
                        'activity_type_id'
                    ]
                )));

            $request->collect('locations')->map(function ($item) use ($person) {
                $location = $person->locations()->forceCreate(Arr::only($item, ['name', 'address']));
                $location->phones()->createMany(Arr::map($item['phones'], fn($phone_number) => compact('phone_number')));
            });

            $tagIds = [];
            $request->collect('tags')->each(function ($item) use (&$tagIds, $person) {
                $tagIds[] = Tag::firstOrCreate(['name' => $item])->id;

                $person->tags()->sync($tagIds);
            });

        });

        return ['message' => __('store-success')];
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

            $person->load(['locations', 'contributors', 'phones', 'tags']);

            $person->update([
                'first_name'    => $request->input('first_name'),
                'last_name'     => $request->input('last_name'),
                'national_code' => $request->input('national_code'),
                'mobile'        => $request->input('mobile'),
                //'email'         => $request->input('email'),
                'birthdate'     => $request->input('birthdate'),
                'department_id' => $request->input('department_id'),
                'grade_id'      => $request->input('grade_id'),
                'employment_no' => $request->input('employment_no')
            ]);


            $newContributors    = $request->collect('contributors')->filter(fn($input) => !isset($input['id']));
            $updateContributors = $request->collect('contributors')->filter(fn($input) => isset($input['id']));

            $person->contributors()->whereNotIn('id', $updateContributors->pluck('id'))->delete();

            $updateContributors->each(fn($item) => $person->contributors()->where('id', $item['id'])->update($item));

            if ($newContributors->count() > 0) {
                $newContributors->each(fn($item) => $person->contributors()->createMany($newContributors->toArray()));
            }


            $newLocations    = $request->collect('locations')->filter(fn($input) => !isset($input['id']));
            $updateLocations = $request->collect('locations')->filter(fn($input) => isset($input['id']));

            $person->locations()->whereNotIn('id', $updateLocations->pluck('id'))->delete();

            $updateLocations->each(fn($item) => $person->locations()->where('id', $item['id'])
                                                       ->update(Arr::only($item, ['name', 'address'])));

            if ($newLocations->count() > 0) {
                //createMany?
                //$locations = $newLocations->each(fn($item) => $person->locations()->forceCreate($newLocations->toArray()));
                //$location = $person->locations()->forceCreate(Arr::only((array)$newLocations, ['name', 'address']));
                //$location = $person->locations()->forceCreate($newLocations->toArray());

                $newLocations->map(function ($item) use ($person) {
                    $location = $person->locations()->forceCreate(Arr::only($item, ['name', 'address']));

                    $person->phones()->delete();
                    $location->phones()->createMany(Arr::map($item['phones'], fn($phone_number) => compact('phone_number')));
                });

            }

            $tagIds = [];
            $request->collect('tags')->each(function ($item) use (&$tagIds, $person) {
                $tagIds[] = Tag::firstOrCreate(['name' => $item])->id;

                $person->tags()->sync($tagIds);
            });
        });

        return ['message' => __('update-success')];

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
            'email'                           => 'email', 'unique:people,email', Rule::requiredIf(empty($person) && is_null($person)),
            //'email'                           => Rule::requiredIf(empty($person) && is_null($person)), 'email',
            //'email'                           => ['required', 'email', Rule::unique('people', 'email')->ignore($person?->id)],
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



    /************* Just Backup **************/
    public function store1(Request $request)
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

    public function update1(Request $request, Person $person)
    {
        $this->validationRequest($request, $person);

        DB::transaction(function () use ($request, $person) {

            $person->load([
                'locations', 'contributors', 'phones', 'tags'
            ]);

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

            /* dd(
                 //$person->whereHas('phones')->get()
                 $person->whereHas('phones', function($query) use ($person) {
                  $query->where('location_id', 93)
                        ->where('person_id', $person->id)
                        ->get();
                 })
             );*/


            //contributors
            $newContributors     = $request->collect('contributors')->filter(fn(array $input) => !isset($input['id']));
            $updatedContributors = $request->collect('contributors')->filter(fn(array $input) => isset($input['id']));

            //contributors
            $person->contributors()->whereNotIn('id', $updatedContributors->pluck('id')->toArray())->delete();

            if ($newContributors->count() > 0) {
                $person->contributors()->createMany($newContributors->toArray());
            }

            $updatedContributors->each(fn(array $input) => $person->contributors()->where('id', $input['id'])->update($input));


            //locations
            $newLocations    = $request->collect('locations')->filter(fn(array $input) => !isset($input['id']));
            $updateLocations = $request->collect('locations')->filter(fn(array $input) => isset($input['id']));

            $person->locations()->whereNotIn('id', $updateLocations->pluck('id')->toArray())->delete();

            $locations = [];
            if ($newLocations->count() > 0) {

                $locations = $person->locations()->createMany($newLocations->toArray());

                $newLocations->each(function ($location) use ($person, $locations) {

                    $person->locations()->with('phones')
                           ->createMany(Arr::map($location['phones'], fn($phone_number) => compact('phone_number')));
                    //phones()->createMany(Arr::map($data['phones'], fn($phone_number) => compact('phone_number')));

                });

            }

            $updateLocations->each(fn($input) => $person->locations()->where('id', $input['id'])->update($input));


            //phones
            /*$person->phones()->delete();

            //$newLocations -> insert phones
            $newLocations->each(function ($input) use (&$locations, $person) {
                dd($person->locations());

            });*/


            //updateLocations -> insert phones


            //$location->phones()->createMany(Arr::map($data['phones'], fn($phone_number) => compact('phone_number')));

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

        return ['message' => __('update-success')];

    }

}
