<?php

namespace docs;

use App\Http\Controllers\Controller;
use App\Http\Resources\Document\DocumentResource;
use App\Http\Resources\Document\DocumentSingleResource;
use App\Http\Resources\Document\GetTextResource;
use App\Models\Base\DocumentKind;
use App\Models\Base\Language;
use App\Models\Base\PublishMethod;
use App\Models\Base\Subject;
use App\Models\Base\Tag;
use App\Models\Document\Document;
use App\Models\Document\DocumentDirector;
use App\Models\Document\DocumentOwner;
use App\Models\Document\DocumentSignatory;
use App\Models\System\Gender;
use Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class DocumentController extends Controller
{
    /**
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        [
            'page'       => $page,
            'per_page'   => $perPage,
            'sort_field' => $sortField,
            'sort_order' => $sortOrder
        ] = $request->all();

        $documents = Document::applySort($sortField, $sortOrder)
                             ->with([
                                 'profiles'      => fn($builder) => $builder->where('id', '=', active_profile()->id),
                                 'documentKind',
                                 'documentLanguage',
                                 'documentOwner' => [
                                     'profile' => [
                                         'department' => [
                                             'departmentCategory',
                                             'departmentType'
                                         ]
                                     ]
                                 ]
                             ])
                             ->paginate(
                                 $perPage ?? 10,
                                 ['*'],
                                 'page',
                                 $page ?? 1,
                             );

        return DocumentResource::collection($documents);
    }


    /**
     * @param Document $document
     * @return DocumentSingleResource
     */
    public function get(Document $document)
    {
        $document->load([
            'documentLanguage',
            'documentKind',
            'documentIssueOrigin',
            'documentPublishMethod',
            'documentTextGenre',
            'documentDirectors',
            'documentSignatories',
            'subjects'
        ]);
        return new DocumentSingleResource(Document::find($document->id));
    }


    /**
     * @param Request $request
     * @return array
     */
    public function store(Request $request)
    {
        $this->validateRequest($request);

        DB::transaction(function () use ($request) {
            $document = Document::create([
                'text_genre_id'     => $request->input('text_genre_id'),
                'language_id'       => $request->input('language_id'),
                'issue_origin_id'   => $request->input('issue_origin_id'),
                'text_type_id'      => $request->input('text_type_id'),
                'document_kind_id'  => $request->input('document_kind_id'),
                'publish_method_id' => $request->input('publish_method_id'),
                'body'              => $request->string('body'),
                'title'             => $request->string('title'),
                'published_at'      => $request->date('published_at')
            ]);
            //$document = Document::find($document->id);

            $request->collect('signatories')
                //forceCreate
                    ->each(fn($signatory) => DocumentSignatory::create([
                        'document_id' => $document->id,
                        'gender_id'   => $signatory['gender_id'],
                        'first_name'  => $signatory['first_name'],
                        'last_name'   => $signatory['last_name']
                    ]));

            $request->collect('directors')
                    ->each(fn($directory) => DocumentDirector::create([
                        'document_id' => $document->id,
                        'gender_id'   => $directory['gender_id'],
                        'first_name'  => $directory['first_name'],
                        'last_name'   => $directory['last_name']
                    ]));

            $document->subjects()->sync($request->input('subject_ids', []));
            $document->tags()->syncWithPivotValues
            (
                $request->input('tag_ids', []),
                [
                    'profile_id' => active_profile()->id
                ]
            );

            DocumentOwner::create([
                'profile_id'  => active_profile()->id,
                'document_id' => $document->id
            ]);
        });

        return [
            'message' => __('messages.store-success')
        ];
    }


    /**
     * @param Request  $request
     * @param Document $document
     * @return array
     */
    public function update(Request $request, Document $document)
    {
        $this->validateRequest($request);

        DB::transaction(function () use ($request, $document) {

            $document->load([
                'documentDirectors',
                'documentSignatories'
            ]);

            $document->tags()
                     ->syncWithPivotValues
                     (
                         $request->input('tag_ids', []),
                         [
                             'profile_id' => active_profile()->id
                         ]
                     );
            $document->subjects()->sync($request->input('subject_ids', []));

            $document->update([
                'text_genre_id'     => $request->input('text_genre_id'),
                'language_id'       => $request->input('language_id'),
                'issue_origin_id'   => $request->input('issue_origin_id'),
                'text_type_id'      => $request->input('text_type_id'),
                'publish_method_id' => $request->input('publish_method_id'),
            ]);


            $inputDirectors = $request->collect('directors');
            $document
                ->documentDirectors()
                ->whereNotIn('id', $inputDirectors->filter(fn($item) => isset($item['id']))
                                                  ->pluck('id')
                                                  ->toArray())
                ->delete();

            $inputDirectors->filter(fn($input) => isset($input['id']))
                           ->each(fn(array $input) => $document
                               ->documentDirectors()
                               ->whereId($input['id'])
                               ->update(Arr::only($input, [
                                   'first_name',
                                   'last_name',
                                   'gender_id'
                               ]))
                           );

            $inputDirectors
                ->filter(fn(array $input) => !isset($input['id']))
                ->whenNotEmpty(function (Collection $items) use ($document) {
                    $document
                        ->documentDirectors()
                        ->createMany(
                            $items
                                ->map(fn(array $item) => Arr::only($item, [
                                    'first_name',
                                    'last_name',
                                    'gender_id'
                                ]))
                                ->toArray()
                        );
                });

            $inputSignatories = $request->collect('signatories');
            $document
                ->documentSignatories()
                ->whereNotIn('id', $inputSignatories->filter(fn($item) => isset($item['id']))
                                                    ->pluck('id')
                                                    ->toArray())
                ->delete();

            $inputSignatories->filter(fn($input) => isset($input['id']))
                             ->each(fn(array $input) => $document
                                 ->documentSignatories()
                                 ->whereId($input['id'])
                                 ->update(Arr::only($input, [
                                     'first_name',
                                     'last_name',
                                     'gender_id'
                                 ]))
                             );

            $inputSignatories
                ->filter(fn(array $input) => !isset($input['id']))
                ->whenNotEmpty(fn(Collection $items) => $document
                    ->documentSignatories()
                    ->createMany(
                        $items
                            ->map(fn(array $item) => Arr::only($item, [
                                'first_name',
                                'last_name',
                                'gender_id'
                            ]))
                            ->toArray()
                    )
                );

        });

        return [
            'message' => __('messages.update-success')
        ];
    }


    /**
     * @param Document $document
     * @return array
     */
    public function addToFavorites(Document $document)
    {
        $activeProfile = active_profile();
        $document->profiles()->attach($activeProfile->id);
        return [
            'message' => __('messages.store-success')
        ];
    }


    /**
     * @param Document $document
     * @return array
     */
    public function removeFromFavorites(Document $document)
    {
        $activeProfile = active_profile();
        $document->profiles()->detach($activeProfile->id);
        return [
            'message' => __('messages.destroy-success')
        ];
    }


    /**
     * @param $request
     * @return void
     */
    public function validateRequest($request)
    {
        $isUpdate = !!$request->route()->parameter('document')?->id;
        $rules    = [
            'text_genre_id'            => ['sometimes', 'required', 'integer', Rule::modelExists('text_genres')],
            'language_id'              => ['sometimes', 'required', 'integer', Rule::modelExists(Language::class)],
            'issue_origin_id'          => ['sometimes', 'required', 'integer', Rule::modelExists('issue_origins')],
            'text_type_id'             => ['sometimes', 'required', 'integer', Rule::modelExists('text_types')],
            'publish_method_id'        => ['sometimes', 'required', 'integer', Rule::modelExists(PublishMethod::class)],
            'subject_ids.*'            => ['sometimes', 'required', 'integer', Rule::modelExists(Subject::class)],
            'tag_ids.*'                => ['sometimes', 'required', 'integer', Rule::modelExists(Tag::class)],
            'signatories.*.gender_id'  => ['sometimes', 'required', Rule::modelExists(Gender::class)],
            'signatories.*.first_name' => 'sometimes|required|string|max:50',
            'signatories.*.last_name'  => 'sometimes|required|string|max:50',
            'directories.*.gender_id'  => ['sometimes', 'required', Rule::modelExists(Gender::class)],
            'directories.*.first_name' => 'sometimes|required|string|max:50',
            'directories.*.last_name'  => 'sometimes|required|string|max:50'
        ];

        if (!$isUpdate) {
            $rules = [
                ...$rules,
                'title'            => 'required|string|max:250',
                'body'             => 'required|string|max:3000',
                'document_kind_id' => ['required', 'integer', Rule::modelExists(DocumentKind::class)],
            ];
        }

        $this->validate($request, $rules);
    }


    /**
     * @param Document $document
     * @return array
     */
    public function destroy(Document $document)
    {
        DB::transaction(function () use ($document) {
            $document->documentSignatories()->delete();
            $document->documentOwner()->delete();
            $document->documentDirectors()->delete();
            $document->tags()->sync([]);
            $document->subjects()->sync([]);
            $document->profiles()->sync([]);
            $document->delete();
        });

        return [
            'message' => __('messages.destroy-success')
        ];
    }


    /**
     * @param Document $document
     * @return GetTextResource
     */
    public function getText(Document $document)
    {
        $document->load([
            'tags' => ['tagType']
        ]);

        return new GetTextResource($document);
    }


    /**
     * @param Document $document
     * @param Request  $request
     * @return array
     */
    public function updateText(Document $document, Request $request)
    {
        $this->validate($request, [
            'body'      => 'required|string|max:3000',
            'title'     => 'required|string|max:250',
            'tag_ids'   => 'required|array',
            'tag_ids.*' => ['sometimes', 'integer', Rule::modelExists(Tag::class)],
        ]);

        DB::transaction(function () use ($document, $request) {
            $document->update([
                'title' => $request->title,
                'body'  => $request->body
            ]);
            $document->tags()->syncWithPivotValues
            (
                $request->input('tag_ids', []),
                [
                    'profile_id' => active_profile()->id
                ]
            );
        });

        return [
            'message' => __('messages.update-success')
        ];
    }


    /**
     * @param Document $document
     * @param Request  $request
     * @return array
     */
    public function addTag(Document $document, Request $request)
    {
        $this->validate($request, [
            'tags'   => 'nullable|array',
            'tags.*' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (is_numeric($value) && Tag::whereId($value)->doesntExist()) {
                        $fail(__('validation.invalid_tag_id'));
                    }
                    if (is_string($value) && !in_array(str($value)->length(), [3, 100])) {
                        $fail(__('validation.invalid_tag_name'));
                    }
                }
            ]
        ]);

        DB::transaction(function () use ($request, $document) {

            $newTagIds     = collect();
            $newTagNames   = $request->collect('tags')->filter(fn($tag) => is_string($tag));
            $syncedTags    = $request->collect('tags')->filter(fn($tag) => is_numeric($tag));
            $activeProfile = active_profile();

            $newTagNames->each(function ($tagName) use ($activeProfile, &$newTagIds) {
                $tag = Tag::firstOrCreate(
                    ['name' => $tagName],
                    ['profile_id' => $activeProfile->id]
                );

                $newTagIds->push($tag->id);
            });

            $document->tags()
                     ->syncWithPivotValues(
                         [
                             ...$newTagIds->toArray(),
                             ...$syncedTags->toArray()
                         ],
                         ['profile_id' => $activeProfile->id]
                     );
        });

        return [
            'message' => __('messages.store-success')
        ];

    }


    /**
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function getFavorites(Request $request)
    {
        [
            'page'       => $page,
            'per_page'   => $perPage,
            'sort_field' => $sortField,
            'sort_order' => $sortOrder
        ] = $request->all();

        $data = active_profile()->documents()
                                ->applySort($sortField, $sortOrder)
                                ->with([
                                    'documentKind',
                                    'documentLanguage',
                                    'documentOwner' => [
                                        'profile' => [
                                            'department' => [
                                                'departmentCategory',
                                                'departmentType'
                                            ]
                                        ]
                                    ]
                                ])
                                ->paginate(
                                    $perPage ?? 10,
                                    ['*'],
                                    'page',
                                    $page ?? 1,
                                );

        return DocumentResource::collection($data);
    }

}
