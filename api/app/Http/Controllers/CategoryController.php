<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    // Small, unpaginated set — a bare { data: [...] }, no meta, id ascending.
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::orderBy('id')->get());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return CategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return CategoryResource::make($category);
    }

    public function destroy(Category $category): Response|JsonResponse
    {
        // Refuse while anything still points here — the restricting foreign keys would
        // otherwise turn the delete into a 500. Counts ride in the message, since the
        // error shape carries no data field.
        $blockers = array_filter([
            $this->tally($category->expenses()->count(), 'expense', 'expenses'),
            $this->tally($category->recurring()->count(), 'standing order', 'standing orders'),
        ]);

        if ($blockers !== []) {
            return response()->json([
                'message' => 'Still tags '.implode(' and ', $blockers).'. Move or delete those first, then it can go.',
            ], 409);
        }

        // Voided rows still hold the restricting foreign key. With their category gone
        // they could never be restored anyway, so they go with it.
        DB::transaction(function () use ($category) {
            $category->expenses()->onlyTrashed()->forceDelete();
            $category->recurring()->onlyTrashed()->forceDelete();
            $category->delete();
        });

        return response()->noContent();
    }

    private function tally(int $count, string $singular, string $plural): ?string
    {
        return $count > 0 ? $count.' '.($count === 1 ? $singular : $plural) : null;
    }
}
