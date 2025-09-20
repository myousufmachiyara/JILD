<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\MeasurementUnit;
use App\Models\ProductVariation;
use App\Models\AttributeValue;
use App\Models\ProductVariationAttributeValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

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
            // Validation
            $validator = Validator::make($request->all(), [
                'selected_variations'   => 'required|array|min:1',
                'selected_variations.*' => 'exists:product_variations,id',
                'quantity'              => 'required|array',
            ]);

            if ($validator->fails()) {
                Log::error('Barcode generation validation failed', [
                    'errors'  => $validator->errors(),
                    'request' => $request->all(),
                ]);
                return back()->withErrors($validator)->withInput();
            }

            $barcodes = [];

            foreach ($request->selected_variations as $variationId) {
                $qty = max(1, (int)($request->quantity[$variationId] ?? 1));
                $variation = ProductVariation::with('product')->findOrFail($variationId);

                $barcodeText = $variation->barcode ?? $variation->sku ?? 'NO-BARCODE';
                $price = number_format($variation->product->selling_price ?? 0, 2);

                $generator = new BarcodeGeneratorPNG();
                $barcodeImage = base64_encode(
                    $generator->getBarcode($barcodeText, $generator::TYPE_CODE_128)
                );

                for ($i = 0; $i < $qty; $i++) {
                    $barcodes[] = [
                        'product'      => $variation->product->name,
                        'variation'    => $variation->name ?? '',
                        'barcodeText'  => $barcodeText,
                        'barcodeImage' => $barcodeImage,
                        'price'        => $price,
                        'sku'          => $variation->sku,
                    ];
                }
            }

            return view('products.multiple-barcodes', compact('barcodes'));

        } catch (\Throwable $e) {
            Log::error('Exception while generating barcodes', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
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
            'name' => 'required|string|max:255|unique:products,name',
            'category_id' => 'required|exists:product_categories,id',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string',
            'description' => 'nullable|string',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'item_type' => 'required|in:fg,raw,service',
            'manufacturing_cost' => 'nullable|numeric',
            'consumption' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'opening_stock' => 'required|numeric',
            'reorder_level' => 'nullable|numeric',
            'max_stock_level' => 'nullable|numeric',
            'minimum_order_qty' => 'nullable|numeric',
            'is_active' => 'boolean',
            'prod_att.*' => 'nullable|image|mimes:jpeg,png,jpg,webp',
        ]);

        DB::beginTransaction();

        try {
            // ✅ Create Product
            $productData = $request->only([
                'name', 'category_id', 'sku', 'barcode', 'description',
                'measurement_unit', 'item_type', 'manufacturing_cost',
                'opening_stock', 'selling_price', 'consumption',
                'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active'
            ]);

            $product = Product::create($productData);
            Log::info('[Product Store] Product created', ['product_id' => $product->id, 'data' => $productData]);

            // ✅ Upload Images
            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);
                    Log::info('[Product Store] Image uploaded', ['product_id' => $product->id, 'path' => $path]);
                }
            }

            // ✅ Variations (FG only)
            if ($request->has('variations')) {
                foreach ($request->variations as $variationData) {
                    $variation = $product->variations()->create([
                        'sku' => $variationData['sku'] ?? null,
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                        'selling_price' => $variationData['selling_price'] ?? 0,
                    ]);
                    Log::info('[Product Store] Variation created', ['variation_id' => $variation->id, 'product_id' => $product->id, 'data' => $variationData]);

                    // Attribute Values
                    if (!empty($variationData['attributes'])) {
                        $variation->attributeValues()->sync($variationData['attributes']);
                        Log::info('[Product Store] Variation attributes synced', [
                            'variation_id' => $variation->id,
                            'attributes' => $variationData['attributes']
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Product created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Product Store] Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);
            return back()->withInput()->with('error', 'Product creation failed. Check logs for details.');
        }
    }

    public function show(Product $product)
    {
        return redirect()->route('products.index');
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

            // ✅ Update product
            $product->update($request->only([
                'name', 'category_id', 'sku', 'measurement_unit', 'item_type',
                'manufacturing_cost', 'opening_stock', 'description', 'selling_price',
                'consumption', 'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active'
            ]));

            $handledVariationIds = [];

            // ✅ Update existing variations
            if (is_array($request->variations)) {
                foreach ($request->variations as $variationData) {
                    $variation = ProductVariation::findOrFail($variationData['id']);
                    $variation->update([
                        'sku' => $variationData['sku'],
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                        'selling_price' => $variationData['selling_price'] ?? 0,
                    ]);

                    if (!empty($variationData['attributes'])) {
                        $variation->attributeValues()->sync($variationData['attributes']);
                    }

                    $handledVariationIds[] = $variation->id;
                }
            }

            // ✅ Add new variations
            if (is_array($request->new_variations)) {
                foreach ($request->new_variations as $newVar) {
                    $variation = $product->variations()->create([
                        'sku' => $newVar['sku'],
                        'manufacturing_cost' => $newVar['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $newVar['stock_quantity'] ?? 0,
                        'selling_price' => $newVar['selling_price'] ?? 0,
                    ]);

                    if (!empty($newVar['attributes'])) {
                        $variation->attributeValues()->sync($newVar['attributes']);
                    }

                    $handledVariationIds[] = $variation->id;
                }
            }

            // ✅ Remove deleted variations
            if ($request->filled('removed_variations')) {
                ProductVariation::whereIn('id', $request->removed_variations)->delete();
            }

            // ✅ Upload new images
            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $file) {
                    $path = $file->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);
                }
            }

            // ✅ Remove images
            if ($request->filled('removed_images')) {
                foreach ($request->removed_images as $id) {
                    $img = $product->images()->find($id);
                    if ($img) {
                        if (\Storage::disk('public')->exists($img->image_path)) {
                            \Storage::disk('public')->delete($img->image_path);
                        }
                        $img->delete();
                    }
                }
            }

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Product updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Product Update] Failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Product update failed. Try again.');
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    public function getByBarcode($barcode)
    {
        try {
            // 1️⃣ Check in ProductVariation
            $variation = ProductVariation::with('product')
                ->where('barcode', $barcode)
                ->first();

            if ($variation) {
                return response()->json([
                    'success' => true,
                    'type' => 'variation',
                    'variation' => [
                        'id' => $variation->id,
                        'product_id' => $variation->product_id,
                        'sku' => $variation->sku,
                        'barcode' => $variation->barcode,
                        'name' => $variation->product->name,
                        'm.cost' => $variation->product->manufacturing_cost,
                    ]
                ]);
            }

            // 2️⃣ Check in Product (raw, service, FG without variations)
            $product = Product::where('barcode', $barcode)->first();

            if ($product) {
                return response()->json([
                    'success' => true,
                    'type' => 'product',
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'barcode' => $product->barcode,
                        'sku' => $product->sku,
                        'm.cost' => $product->manufacturing_cost,
                    ]
                ]);
            }

            // 3️⃣ Not found
            return response()->json([
                'success' => false,
                'message' => 'No product or variation found for this barcode'
            ]);

        } catch (\Exception $e) {
            Log::error('Barcode lookup failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error occurred while searching barcode'
            ]);
        }
    }

    public function getVariations($productId)
    {
        $product = Product::with('variations', 'measurementUnit')->find($productId);

        if (!$product) {
            return response()->json([
                'success'   => false,
                'variation' => [],
            ]);
        }

        $unitId = $product->measurementUnit->id ?? null;

        $variations = $product->variations->map(function ($v) use ($unitId) {
            return [
                'id'   => $v->id,
                'sku'  => $v->sku,
                'unit' => $unitId,
            ];
        })->toArray();

        return response()->json([
            'success'   => true,
            'variation' => $variations,

            // 🔹 Extra: add product info for modules that need it
            'product'   => [
                'id'                 => $product->id,
                'name'               => $product->name,
                'manufacturing_cost' => $product->manufacturing_cost, // ✅ always included
                'unit'               => $unitId,
            ],
        ]);
    }

    public function bulkUploadTemplate()
    {
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=product_bulk_template.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        ];

        $attributes = Attribute::pluck('name')->toArray();
        $categories = ProductCategory::pluck('id', 'name')->toArray();
        $units      = MeasurementUnit::pluck('id', 'name')->toArray();

        $columns = array_merge([
            'Product SKU', 'Product Name', 'Category ID', 'Unit ID', 'Item Type', 'Description',
            'Variation SKU', 'Variation Barcode', 'Variation Price', 'Variation Stock'
        ], $attributes);

        $callback = function() use ($columns, $categories, $units) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $catIds  = array_values($categories);
            $unitIds = array_values($units);

            // Finished goods with variations
            $finishedGoods = [
                ['CHR001', 'Office Chair', 'Ergonomic chair', [['CHR001-B-M','1234567890123','5000','20','Black','Medium'], ['CHR001-W-L','1234567890124','5200','15','White','Large']]],
                ['DSK001', 'Wooden Desk', 'Solid oak office desk', [['DSK001-B-STD','2234567890123','12000','10','Brown','Standard'], ['DSK001-W-STD','2234567890124','12500','8','White','Standard']]],
                ['LPT001', 'Laptop Table', 'Adjustable laptop table', [['LPT001-B-S','3234567890123','3000','30','Black','Small'], ['LPT001-B-L','3234567890124','3500','25','Black','Large']]],
            ];

            foreach ($finishedGoods as $fg) {
                foreach ($fg[3] as $variation) {
                    fputcsv($file, [
                        $fg[0], $fg[1], $catIds[0] ?? 1, $unitIds[0] ?? 1, 'fg', $fg[2],
                        $variation[0], $variation[1], $variation[2], $variation[3],
                        $variation[4] ?? '', $variation[5] ?? ''
                    ]);
                }
            }

            // Raw materials (no variations)
            $rawProducts = [
                ['RAW001', 'Wood Plank', 'Solid oak plank 2x4 feet'],
                ['RAW002', 'Steel Rod', 'High-strength steel rod 12mm'],
                ['RAW003', 'Foam Sheet', 'High density foam sheet'],
                ['RAW004', 'Leather Roll', 'Genuine brown leather roll'],
                ['RAW005', 'Glass Sheet', 'Tempered glass sheet 4x6'],
            ];

            foreach ($rawProducts as $raw) {
                fputcsv($file, [
                    $raw[0], $raw[1], $catIds[1] ?? 1, $unitIds[1] ?? 1, 'raw', $raw[2],
                    '', '', '0', '0'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkExport()
    {
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=products_export.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        ];

        $attributes = Attribute::pluck('name')->toArray();
        $columns = array_merge([
            'Product SKU', 'Product Name', 'Category ID', 'Unit ID', 'Item Type', 'Description',
            'Variation SKU', 'Variation Barcode', 'Variation Price', 'Variation Stock'
        ], $attributes);

        $callback = function() use ($columns, $attributes) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $products = Product::with(['variations.attributeValues.attribute'])->get();

            foreach ($products as $product) {
                if ($product->variations->isEmpty()) {
                    fputcsv($file, [
                        $product->sku, $product->name, $product->category_id, $product->measurement_unit,
                        $product->item_type, $product->description,
                        '', '', 0, 0
                    ]);
                } else {
                    foreach ($product->variations as $variation) {
                        $row = [
                            $product->sku, $product->name, $product->category_id, $product->measurement_unit,
                            $product->item_type, $product->description,
                            $variation->sku, $variation->barcode, $variation->selling_price, $variation->stock_quantity
                        ];

                        foreach ($attributes as $attr) {
                            $value = $variation->attributeValues->firstWhere('attribute.name', $attr)->value ?? '';
                            $row[] = $value;
                        }

                        fputcsv($file, $row);
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,txt',
            'delete_missing' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $rows = Excel::toArray([], $request->file('file'))[0] ?? [];
            if (empty($rows)) throw new \Exception('Uploaded file is empty.');

            $header = array_map('strtolower', array_shift($rows));
            $dbAttributes = Attribute::all()->keyBy(fn($a) => strtolower($a->name));

            $importedSKUs = [];
            $importedVariationSKUs = [];

            foreach ($rows as $row) {
                if (empty($row[0]) && empty($row[1])) continue;
                $rowData = array_combine($header, $row);

                $productSku = trim($rowData['product sku'] ?? '');
                $variationSku = trim($rowData['variation sku'] ?? '');

                $importedSKUs[] = $productSku;
                $importedVariationSKUs[] = $variationSku;

                $product = Product::updateOrCreate(
                    ['sku' => $productSku],
                    [
                        'name' => trim($rowData['product name'] ?? ''),
                        'category_id' => (int)($rowData['category id'] ?? 0),
                        'measurement_unit' => (int)($rowData['unit id'] ?? 0),
                        'item_type' => trim($rowData['item type'] ?? ''),
                        'description' => $rowData['description'] ?? null,
                    ]
                );

                $variation = ProductVariation::updateOrCreate(
                    ['sku' => $variationSku],
                    [
                        'product_id' => $product->id,
                        'barcode' => $rowData['variation barcode'] ?? null,
                        'selling_price' => (float)($rowData['variation price'] ?? 0),
                        'stock_quantity' => (float)($rowData['variation stock'] ?? 0),
                    ]
                );

                $variation->attributeValues()->sync(
                    collect($dbAttributes)->mapWithKeys(function($attribute) use ($rowData) {
                        $value = $rowData[strtolower($attribute->name)] ?? null;
                        if (!$value) return [];
                        $attrValue = AttributeValue::firstOrCreate([
                            'attribute_id' => $attribute->id,
                            'value' => ucfirst(strtolower($value))
                        ]);
                        return [$attrValue->id => []];
                    })->toArray()
                );
            }

            // Delete missing products/variations if checkbox is checked
            if ($request->delete_missing) {
                ProductVariation::whereNotIn('sku', $importedVariationSKUs)->delete();
                Product::whereNotIn('sku', $importedSKUs)->delete();
            }

            DB::commit();
            return back()->with('success', 'Products imported successfully. Deleted missing products/variations: ' . ($request->delete_missing ? 'Yes' : 'No'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Bulk import failed: ' . $e->getMessage());
        }
    }

}

