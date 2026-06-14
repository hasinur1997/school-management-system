<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Category\ListCategoriesRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    public function __construct(private readonly CategoryService $categories) {}

    /**
     * Browse categories in the caller's branch, optionally filtered by type.
     */
    public function index(ListCategoriesRequest $request): JsonResponse
    {
        $categories = $this->categories->list(
            $request->only(['type']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => CategoryResource::collection($categories)->resolve($request),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return $this->success(CategoryResource::make($category), 'Category created', 201);
    }

    /**
     * Update a category. Out-of-branch ids 404 via BranchScope binding.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categories->update($category, $request->validated());

        return $this->success(CategoryResource::make($category), 'Category updated');
    }

    /**
     * Delete a category. A category in use by income/expense rows → 409.
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return $this->success(null, 'Category deleted');
    }
}
