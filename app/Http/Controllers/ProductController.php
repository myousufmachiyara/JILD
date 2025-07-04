<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'variations')->get();
        $categories = ProductCategory::all();
        return view('products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = ProductCategory::all();
        $attributes = Attribute::with('values')->get();

        return view('products.create', compact('categories', 'attributes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:product_categories,id',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'in:active,inactive',
            'measurement_unit' => 'required|string',
            'item_type' => 'required|in:fg,raw',
            'price' => 'required|numeric',
            'opening_stock' => 'required|numeric',
            'prod_att.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        DB::beginTransaction();

        try {
            // Create Product
            $product = Product::create([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'sku' => $request->sku,
                'barcode' => $request->barcode,
                'description' => $request->description,
                'status' => $request->status ?? 'active',
                'measurement_unit' => $request->measurement_unit,
                'item_type' => $request->item_type,
                'price' => $request->price,
                'opening_stock' => $request->opening_stock,
            ]);

            // Log product creation
            Log::info('Product created', ['product_id' => $product->id]);

            // Upload Images
            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);

                    Log::info('Product image uploaded', ['path' => $path, 'product_id' => $product->id]);
                }
            }

            // Handle Variations
            if ($request->has('variations')) {
                foreach ($request->variations as $index => $variationData) {
                    $variation = $product->variations()->create([
                        'sku' => $variationData['sku'] ?? null,
                        'price' => $variationData['price'],
                        'stock' => $variationData['stock'],
                    ]);

                    Log::info('Variation created', ['variation_id' => $variation->id]);

                    if (isset($variationData['attributes'])) {
                        foreach ($variationData['attributes'] as $attr) {
                            $variation->values()->create([
                                'attribute_value_id' => $attr['attribute_value_id'],
                            ]);
                            Log::info('Variation value linked', [
                                'variation_id' => $variation->id,
                                'attribute_value_id' => $attr['attribute_value_id']
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            DB::rollback();

            // Log error
            Log::error('Product creation failed', [
                'error_message' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return back()->withErrors(['error' => 'Product creation failed. Check logs for details.']);
        }
    }
    
    public function show(Product $product)
    {
        $product->load('category', 'variations');
        return view('products.show', compact('product'));
    }

        public function details(Request $request)
    {
        $product = Product::findOrFail($request->id);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->item_code ?? '',      // If you have `item_code`
            'unit' => $product->unit ?? '',           // If your table has `unit`
            'price' => $product->price ?? 0,          // Or get price from variation
        ]);
    }
    
    public function edit($id)
    {
        $product = Product::with(['images', 'variations'])->findOrFail($id);
        $categories = ProductCategory::all();
        $attributes = Attribute::with('values')->get();

        // Group selected attribute values by attribute
    $attributeValues = collect();
    foreach ($attributes as $attribute) {
        foreach ($attribute->values as $val) {
            $val->attribute = $attribute; // attach parent attribute
            $attributeValues->push($val);
        }
    }

        return view('products.edit', compact(
            'product',
            'categories',
            'attributes',
            'attributeValues'
        ));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::findOrFail($id);
            $product->update($request->only([
                'name', 'category_id', 'sku', 'measurement_unit', 'item_type',
                'price', 'opening_stock', 'description'
            ]));

            Log::info('Product updated', ['product_id' => $product->id]);

            $existingVariationIds = $product->variations()->pluck('id')->toArray();

            $incomingVariationIds = [];
            foreach ($request->variations as $variationData) {
                $variationId = $variationData['id'] ?? null;

                $variationPayload = [
                    'sku'   => $variationData['sku'],
                    'price' => $variationData['price'] ?? 0,
                    'stock' => $variationData['stock'] ?? 0,
                ];

                if ($variationId && in_array($variationId, $existingVariationIds)) {
                    // Update existing variation
                    $variation = ProductVariation::find($variationId);
                    $variation->update($variationPayload);
                    $incomingVariationIds[] = $variationId;
                } else {
                    // Check if SKU already exists for this product (to avoid duplicate)
                    $existing = ProductVariation::withTrashed()
                        ->where('product_id', $product->id)
                        ->where('sku', $variationData['sku'])
                        ->first();

                    if ($existing) {
                        // Restore if soft-deleted, and update
                        $existing->restore();
                        $existing->update($variationPayload);
                        $incomingVariationIds[] = $existing->id;
                    } else {
                        // New variation
                        $newVariation = $product->variations()->create($variationPayload);
                        $incomingVariationIds[] = $newVariation->id;
                    }
                }

                // Sync attributes if provided
                if (isset($variationData['attributes'])) {
                    $variation->attributeValues()->sync($variationData['attributes']);
                }
            }

            // Soft delete removed variations
            $variationsToDelete = array_diff($existingVariationIds, $incomingVariationIds);
            ProductVariation::whereIn('id', $variationsToDelete)->delete();

            Log::info('Product variations updated', [
                'product_id' => $product->id,
                'updated_ids' => $incomingVariationIds,
                'deleted_ids' => $variationsToDelete
            ]);

            DB::commit();

            return redirect()->route('products.index')->with('success', 'Product updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Product update failed', [
                'error_message' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);
            return redirect()->back()->with('error', 'Product update failed. Please try again.');
        }
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

}
