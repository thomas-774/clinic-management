<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Enums\DrugCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StoreDrugRequest;
use App\Http\Requests\Doctor\UpdateDrugRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\DrugResource;
use App\Http\Resources\DrugSuggestionResource;
use App\Models\Drug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The prescription drug catalogue (Module J). There is no delete: old
 * prescriptions keep their link, so a drug is hidden instead (RX-3).
 */
class DrugController extends Controller
{
    public const MIN_QUERY_LENGTH = 2;

    public const MAX_SUGGESTIONS = 15;

    public const PER_PAGE = 20;

    /**
     * GET /doctor/drugs/search?q= — typeahead over active drugs (FR-J.2, RX-3).
     */
    public function search(Request $request): ApiResourceCollection
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return DrugSuggestionResource::collection(collect());
        }

        $drugs = Drug::query()
            ->active()
            ->matching($q)
            ->rankedFor($q)
            ->limit(self::MAX_SUGGESTIONS)
            ->get();

        return DrugSuggestionResource::collection($drugs);
    }

    /**
     * GET /doctor/drugs?search=&category=&include_hidden= — Settings → Drugs (FR-J.6).
     */
    public function index(Request $request): ApiResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', Rule::enum(DrugCategory::class)],
            'include_hidden' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) $request->query('search', ''));

        $drugs = Drug::query()
            ->unless($request->boolean('include_hidden'), fn ($query) => $query->active())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->query('category')))
            ->when($search !== '', fn ($query) => $query->matching($search))
            ->orderBy('trade_name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return DrugResource::collection($drugs);
    }

    /**
     * GET /doctor/drugs/{drug} — the side note; hidden drugs are still readable (FR-J.3, RX-3).
     */
    public function show(Drug $drug): DrugResource
    {
        return DrugResource::make($drug);
    }

    /**
     * POST /doctor/drugs
     */
    public function store(StoreDrugRequest $request): JsonResponse
    {
        $drug = Drug::create($request->drugData());

        return DrugResource::make($drug->refresh())
            ->withMessage(__('Drug added.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /doctor/drugs/{drug} — edit, or hide / show with `is_active`.
     */
    public function update(UpdateDrugRequest $request, Drug $drug): DrugResource
    {
        $drug->update($request->drugData());

        return DrugResource::make($drug->refresh())->withMessage(__('Drug updated.'));
    }
}
