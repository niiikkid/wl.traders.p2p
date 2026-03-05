<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /**
     * Отображает список категорий.
     */
    public function index()
    {
        $categories = Category::query()
            ->orderBy('name')
            ->paginate(request()->per_page ?? 10);

        $categories = CategoryResource::collection($categories);

        return Inertia::render('Category/Index', compact('categories'));
    }

    /**
     * Отображает форму создания новой категории.
     */
    public function create()
    {
        return Inertia::render('Category/Create');
    }

    /**
     * Сохраняет новую категорию.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Категория успешно создана.');
    }

    /**
     * Отображает форму редактирования категории.
     */
    public function edit(Category $category)
    {
        return Inertia::render('Category/Edit', [
            'category' => (new CategoryResource($category))->resolve(),
        ]);
    }

    /**
     * Обновляет категорию.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Категория успешно обновлена.');
    }

    /**
     * Удаляет категорию.
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Категория успешно удалена.');
    }
}
