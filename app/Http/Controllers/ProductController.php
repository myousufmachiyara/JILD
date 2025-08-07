<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\MeasurementUnit;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorPNG;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'variations')->get();
        $categories = ProductCategory::all();
        return view('products.index', compact('products', 'categories'));
    }

    public function barcodeSelection()
    {
        $variations = ProductVariation::with('product')->get();
        return view('products.barcode-selection', compact('variations'));
    }

    public function generateMultipleBarcodes(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'variations' => 'required|array',
                'variations.*' => 'required|array',
                'variations.*.id' => 'required|exists:product_variations,id',
                'variations.*.quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                Log::error('Barcode generation validation failed', [
                    'errors' => $validator->errors(),
                    'request' => $request->all(),
                ]);
                return back()->withErrors($validator)->withInput();
            }

            $barcodes = [];

            foreach ($request->variations as $item) {
                $variation = ProductVariation::with('product')->findOrFail($item['id']);

                $barcodeText = $variation->barcode ?? $variation->sku ?? 'NO-BARCODE';
                $price = number_format($variation->product->selling_price ?? 0, 2);

                $generator = new BarcodeGeneratorPNG();
                $barcodeImage = base64_encode($generator->getBarcode($barcodeText, $generator::TYPE_CODE_128));

                for ($i = 0; $i < $item['quantity']; $i++) {
                    $barcodes[] = [
                        'product' => $variation->product->name,
                        'variation' => $variation->name ?? '',
                        'barcodeText' => $barcodeText,
                        'barcodeImage' => $barcodeImage,
                        'price' => $price,
                        'sku' => $variation->sku,
                    ];
                }
            }

            return view('products.multiple-barcodes', compact('barcodes'));

        } catch (\Throwable $e) {
            Log::error('Exception while generating barcodes', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return back()->with('error', 'Something went wrong while generating barcodes. Check logs for details.');
        }
    }

    public function create()
    {
        $categories = ProductCategory::all();
        $attributes = Attribute::with('values')->get();
        $units = MeasurementUnit::all();

        return view('products.create', compact('categories', 'attributes', 'units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:product_categories,id',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string',
            'description' => 'nullable|string',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'item_type' => 'required|in:fg,raw',
            'manufacturing_cost' => 'nullable|numeric',
            'consumption' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'opening_stock' => 'required|numeric',
            'prod_att.*' => 'nullable|image|mimes:jpeg,png,jpg,webp',
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
                'measurement_unit' => $request->measurement_unit,
                'selling_price' => $request->selling_price,
                'consumption' => $request->consumption,
                'item_type' => $request->item_type,
                'manufacturing_cost' => $request->manufacturing_cost,
                'opening_stock' => $request->opening_stock,
            ]);

            Log::info('[Product Store] Product created', [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ]);

            // Upload Images
            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);

                    Log::info('[Product Store] Image uploaded', [
                        'product_id' => $product->id,
                        'file_path' => $path
                    ]);
                }
            }

            // Handle Variations
            if ($request->has('variations')) {
                foreach ($request->variations as $index => $variationData) {
                    $variation = $product->variations()->create([
                        'sku' => $variationData['sku'] ?? null,
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                    ]);

                    Log::info('[Product Store] Variation created', [
                        'variation_id' => $variation->id,
                        'product_id' => $product->id,
                    ]);

                    // Attribute Values
                    if (!empty($variationData['attributes'])) {
                        foreach ($variationData['attributes'] as $attr) {
                            $variation->values()->create([
                                'attribute_value_id' => $attr['attribute_value_id'],
                            ]);

                            Log::info('[Product Store] Variation attribute linked', [
                                'variation_id' => $variation->id,
                                'attribute_value_id' => $attr['attribute_value_id'],
                            ]);
                        }
                    } else {
                        Log::warning('[Product Store] Variation created without attributes', [
                            'variation_id' => $variation->id,
                        ]);
                    }
                }
            } else {
                Log::warning('[Product Store] No variations submitted', ['product_id' => $product->id]);
            }

            DB::commit();

            return redirect()->route('products.index')->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[Product Store] Failed to create product', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['prod_att', '_token']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Product creation failed. Please try again or contact support.');
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
        $product = Product::with(['images', 'variations.attributeValues'])->findOrFail($id);
        $categories = ProductCategory::all();
        $attributes = Attribute::with('values')->get();
        $units = MeasurementUnit::all(); // ✅ Add this line

        // Optional: attach parent attribute (if needed for UI or JS)
        $attributeValues = collect();
        foreach ($attributes as $attribute) {
            foreach ($attribute->values as $val) {
                $val->attribute = $attribute;
                $attributeValues->push($val);
            }
        }

        return view('products.edit', compact(
            'product',
            'categories',
            'attributes',
            'attributeValues',
            'units' // ✅ Pass to view
        ));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::findOrFail($id);

            $product->update($request->only([
                'name', 'category_id', 'sku', 'measurement_unit', 'item_type',
                'manufacturing_cost', 'opening_stock', 'description', 'selling_price', 'consumption'
            ]));

            Log::info('[Product Update] Product updated', ['product_id' => $product->id]);

            $existingVariationIds = $product->variations()->pluck('id')->toArray();
            $handledVariationIds = [];

            // ✅ Handle existing variations
            if (is_array($request->variations)) {
                foreach ($request->variations as $index => $variationData) {
                    if (empty($variationData['sku'])) {
                        return redirect()->back()
                            ->withInput()
                            ->with('error', "Variation at row {$index} is missing SKU.");
                    }

                    $variationId = $variationData['id'] ?? null;

                    $variationPayload = [
                        'sku' => $variationData['sku'],
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                    ];

                    try {
                        $variation = ProductVariation::findOrFail($variationId);
                        $variation->update($variationPayload);

                        if (!empty($variationData['attributes']) && is_array($variationData['attributes'])) {
                            $variation->attributeValues()->sync($variationData['attributes']);
                        }

                        $handledVariationIds[] = $variation->id;

                    } catch (\Throwable $ve) {
                        Log::error('[Product Update] Variation update error', [
                            'product_id' => $product->id,
                            'variation_data' => $variationData,
                            'message' => $ve->getMessage()
                        ]);
                        return redirect()->back()
                            ->withInput()
                            ->with('error', 'Error updating variation: ' . $ve->getMessage());
                    }
                }
            }

            // ✅ Handle newly added variations
            if (is_array($request->new_variations)) {
                foreach ($request->new_variations as $index => $newVar) {
                    if (empty($newVar['sku'])) {
                        return redirect()->back()
                            ->withInput()
                            ->with('error', "New variation at row {$index} is missing SKU.");
                    }

                    try {
                        $variation = $product->variations()->create([
                            'sku' => $newVar['sku'],
                            'manufacturing_cost' => $newVar['manufacturing_cost'] ?? 0,
                            'stock_quantity' => $newVar['stock_quantity'] ?? 0,
                        ]);

                        if (!empty($newVar['attributes']) && is_array($newVar['attributes'])) {
                            $variation->attributeValues()->sync($newVar['attributes']);
                        }

                        $handledVariationIds[] = $variation->id;

                    } catch (\Throwable $ne) {
                        Log::error('[Product Update] New variation create error', [
                            'product_id' => $product->id,
                            'variation_data' => $newVar,
                            'message' => $ne->getMessage()
                        ]);
                        return redirect()->back()
                            ->withInput()
                            ->with('error', 'Error saving new variation: ' . $ne->getMessage());
                    }
                }
            }

            // ✅ Soft delete removed variations
            $variationsToDelete = array_diff($existingVariationIds, $handledVariationIds);
            if (!empty($variationsToDelete)) {
                ProductVariation::whereIn('id', $variationsToDelete)->delete();

                Log::info('[Product Update] Variations soft deleted', [
                    'product_id' => $product->id,
                    'deleted_ids' => $variationsToDelete
                ]);
            }

            DB::commit();

            return redirect()->route('products.index')->with('success', 'Product updated successfully.');
        } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('[Product Update] Failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->back()->withInput()->with('error', 'Product update failed. Please try again.');
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}
