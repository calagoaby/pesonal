<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
        // Refuse while any expense still points here; the count rides in the message
        // since the error shape carries no data field.
        $used = $category->expenses()->count();

        if ($used > 0) {
            $noun = $used === 1 ? 'expense' : 'expenses';

            return response()->json([
                'message' => "Still tags {$used} {$noun}. Move or delete those first, then it can go.",
            ], 409);
        }

        $category->delete();

        return response()->noContent();
    }
}
