<?php

namespace App\Http\Controllers;

use App\Http\Resources\DepartmentResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductListResource;
use App\Models\Department;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function home(Request $request)
    {
        $keyword = $request->query('keyword');
        $products = Product::query()
            ->forWebsite()
            ->when($keyword, function ($query, $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('title', 'LIKE', "%{$keyword}%")
                        ->orWhere('description', 'LIKE', "%{$keyword}%");
                });
            })
            ->paginate(12);

        return Inertia::render('Home', [
            'products' => ProductListResource::collection($products)
        ]);
    }

    public function show(Product $product)
{
    $product->load([
        'media',
        'variationTypes.options.media',
        'variations',
        'user',
        'department',
    ]);

    // Debug media
    foreach ($product->getMedia() as $media) {
        Log::info("Product media path", [
            'product_id' => $product->id,
            'media_id' => $media->id,
            'url' => $media->getUrl(),
            'file_name' => $media->file_name,
            'path' => $media->getPath(),
            'exists' => file_exists($media->getPath()),
        ]);
    }

    return inertia::render('Product/Show', [
        'product' => new ProductResource($product),
        'variationOptions' => request('options', [])
    ]);
}

    public function byDepartment(Request $request, Department $department)
    {
        abort_unless($department->active, 404);

        $keyword = $request->query('keyword');
        $products = Product::query()
            ->forWebsite()
            ->where('department_id', $department->id)
            ->when($keyword, function ($query, $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('title', 'LIKE', "%{$keyword}%")
                        ->orWhere('description', 'LIKE', "%{$keyword}%");
                });
            })
            ->paginate();

        return Inertia::render('Department/Index', [
            'department' => new DepartmentResource($department),
            'products' => ProductListResource::collection($products),
        ]);
    }
}
