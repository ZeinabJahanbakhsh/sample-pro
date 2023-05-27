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

        $person = Person::with([
            'contributors',
            'locations',
            'tags'
        ])->paginate(
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
                ))
            );

            $request->collect('locations')->map(function ($item) use ($person) {

                $location = $person->locations()
                                   ->forceCreate(Arr::only($item, ['name', 'address']));

                $location->phones()
                         ->createMany(
                             Arr::map($item['phones'], fn($phone_number) => compact('phone_number'))
                         );
            });

            $tagIds = [];
            $request->collect('tags')->each(function ($item) use (&$tagIds, $person) {

                $tagIds[] = Tag::firstOrCreate(['name' => $item])->id;
            });

            $person->tags()->sync($tagIds);

        });

        return [
            'message' => __('messages.store-success')
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

        DB::transaction(function () use ($person, $request) {

            $person->update([
                'first_name'    => $request->input('first_name'),
                'last_name'     => $request->input('last_name'),
                'national_code' => $request->input('national_code'),
                'mobile'        => $request->input('mobile'),
                'birthdate'     => $request->date('birthdate'),
                'department_id' => $request->input('department_id'),
                'grade_id'      => $request->input('grade_id'),
                'employment_no' => $request->input('employment_no'),
            ]);


            $updatedContributors = $request->collect('contributors')->filter(fn($item) => isset($item['id']));
            $newContributors     = $request->collect('contributors')->filter(fn($item) => !isset($item['id']));

            $person->contributors()->whereNotIn('id', $updatedContributors->pluck('id'))->delete();
            $updatedContributors->each(fn($item) => $person->contributors()
                                                           ->where('id', $item['id'])
                                                           ->update(
                                                               Arr::only($item, [
                                                                   'first_name',
                                                                   'last_name',
                                                                   'employment_no',
                                                                   'started_at',
                                                                   'finished_at',
                                                                   'activity_type_id'
                                                               ]))
            );


            if ($newContributors->count() > 0) {
                $newContributors->each(fn($item) => $person->contributors()
                                                           ->create($item));
            }


            $updatedLocations = $request->collect('locations')->filter(fn($item) => isset($item['id']));
            $newLocations     = $request->collect('locations')->filter(fn($item) => !isset($item['id']));

            //extraLocationIds
            $person->locations()->whereNotIn('id', $updatedLocations->pluck('id'))->delete();

            //extraPhoneIds
            $person->phones()->whereNotIn('location_id', $updatedLocations->pluck('id'))->delete();


            $updatedLocations->each(function ($item) use ($person) {

                $person->locations()->where('id', $item['id'])
                       ->update(Arr::only($item, ['name', 'address']));

                $locationId = $person->locations()
                                     ->firstWhere('id', $item['id']);

                $locationId->phones()->delete();

                $locationId->phones()->createMany(
                    Arr::map(
                        $item['phones'],
                        fn($phone_number) => compact('phone_number')
                    )
                );

            });


            if ($newLocations->count() > 0) {

                $newLocations->map(function ($item) use ($person) {
                    $locations = $person->locations()
                                        ->forceCreate(Arr::only($item, ['name', 'address']));

                    $locations->phones()
                              ->createMany(
                                  Arr::map(
                                      $item['phones'],
                                      fn($phone_number) => compact('phone_number'))
                              );

                });
            }


            $tagIds = [];
            $request->collect('tags')->each(function ($item) use ($person, &$tagIds) {
                $tagIds[] = Tag::firstOrCreate(['name' => $item])->id;
            });
            $person->tags()->sync($tagIds);

        });

        return [
            'message' => __('messages.update-success')
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
            'message' => __('messages.destroy-success')
        ];
    }


    /**
     * @param Request     $request
     * @param Person|null $person
     * @return void
     */
    private function validationRequest(Request $request, Person $person = null)
    {
        $this->validate($request, [
            'email'                           => ['email', Rule::requiredIf(fn() => empty($person) && is_null($person)), Rule::unique('people', 'email')],
            'first_name'                      => ['required', 'max:50', 'persian_alpha'],
            'last_name'                       => ['required', 'max:50', 'persian_alpha'],
            'national_code'                   => ['required', 'ir_national_code'],
            'mobile'                          => ['nullable', 'ir_mobile'],
            'birthdate'                       => ['nullable', 'date', 'before:now'],
            'department_id'                   => ['nullable', Rule::modelExists(Department::class)],
            'grade_id'                        => ['nullable', Rule::modelExists(Grade::class)],
            'employment_no'                   => ['nullable', 'digits_between:1,7'],
            'contributors.*'                  => ['required', 'array'],
            'contributors.*.first_name'       => ['required', 'max:50', 'persian_alpha'],
            'contributors.*.last_name'        => ['required', 'max:50', 'persian_alpha'],
            'contributors.*.employment_no'    => ['nullable', 'integer', 'digits_between:1,7'],
            'contributors.*.started_at'       => ['nullable', 'date'],
            'contributors.*.finished_at'      => ['nullable', 'date'],
            'contributors.*.activity_type_id' => ['nullable', Rule::modelExists(ActivityType::class)],
            'locations.*'                     => ['required', 'array'],
            'locations.*.city_id'             => ['nullable', Rule::modelExists(City::class)],
            'locations.*.name'                => ['nullable', 'persian_alpha'],
            'locations.*.address'             => ['nullable', 'persian_alpha'],
            'locations.*.phones.*'            => ['nullable'],
            'tags'                            => ['required', 'array']
        ]);
    }



    /***************************************************************************************************/
    /***************************************************************************************************/
    /*********************************************03/03/1402******************************************************/
    public function store2(Request $request)
    {
        $this->validationRequest($request);

        DB::transaction(function () use ($request) {

            $person = Person::forceCreate([
                'first_name'    => $request->input('first_name'),
                'last_name'     => $request->input('last_name'),
                'national_code' => $request->input('national_code'),
                'email'         => $request->input('email'),
                'birthdate'     => $request->input('birthdate'),
                'mobile'        => $request->input('mobile'),
                'employment_no' => $request->input('employment_no'),
                'grade_id'      => $request->input('grade_id'),
                'department_id' => $request->input('department_id'),
            ]);

            $person->contributors()->createMany(
                $request->collect('contributors')
                        ->each(fn($item) => Arr::only($item, [
                            'first_name',
                            'last_name',
                            'employment_no',
                            'started_at',
                            'finished_at',
                            'activity_type_id'
                        ]))
            );

            $request->collect('locations')->each(function ($item) use ($person) {
                $location = $person->locations()->forceCreate([
                    'name'    => $item['name'],
                    'address' => $item['address'],
                ]);

                $location->phones()->createMany(
                    Arr::map($item['phones'], fn($phone_number) => compact('phone_number'))
                );
            });

            $tagIds = [];
            $request->collect('tags')->each(function ($item) use (&$tagIds, $person) {
                $tagIds[] = Tag::firstOrCreate([
                    'name' => $item
                ])->id;
                //pluck('id)->wrong
            });
            $person->tags()->sync($tagIds);

        });

        return [
            'messages' => __('messages.store-success')
        ];

    }


}
