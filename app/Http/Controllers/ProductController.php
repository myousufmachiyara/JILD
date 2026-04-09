<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ChartOfAccounts;
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

                $barcodeText  = $variation->barcode ?? $variation->sku ?? 'NO-BARCODE';
                $price        = number_format($variation->product->selling_price ?? 0, 2);
                $generator    = new BarcodeGeneratorPNG();
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
        $units      = MeasurementUnit::all();
        $vendors = ChartOfAccounts::whereIn('account_type', ['customer', 'vendor'])->orderBy('name')->get();

        return view('products.create', compact('categories', 'attributes', 'units', 'vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255|unique:products,name',
            'category_id'       => 'required|exists:product_categories,id',
            'vendor_id'         => 'nullable|exists:chart_of_accounts,id', // ← NEW
            'sku'               => 'required|string|unique:products,sku',
            'barcode'           => 'nullable|string',
            'description'       => 'nullable|string',
            'measurement_unit'  => 'required|exists:measurement_units,id',
            'item_type'         => 'required|in:fg,raw,service',
            'manufacturing_cost'=> 'nullable|numeric',
            'consumption'       => 'nullable|numeric',
            'selling_price'     => 'nullable|numeric',
            'opening_stock'     => 'required|numeric',
            'reorder_level'     => 'nullable|numeric',
            'max_stock_level'   => 'nullable|numeric',
            'minimum_order_qty' => 'nullable|numeric',
            'is_active'         => 'boolean',
            'prod_att.*'        => 'nullable|image|mimes:jpeg,png,jpg,webp',
        ]);

        DB::beginTransaction();

        try {
            $productData = $request->only([
                'name', 'category_id', 'vendor_id', 'sku', 'barcode', 'description', // ← vendor_id added
                'measurement_unit', 'item_type', 'manufacturing_cost',
                'opening_stock', 'selling_price', 'consumption',
                'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active',
            ]);

            $product = Product::create($productData);
            Log::info('[Product Store] Product created', ['product_id' => $product->id]);

            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);
                }
            }

            if ($request->has('variations')) {
                foreach ($request->variations as $variationData) {
                    $variation = $product->variations()->create([
                        'sku'                => $variationData['sku'] ?? null,
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity'     => $variationData['stock_quantity'] ?? 0,
                        'selling_price'      => $variationData['selling_price'] ?? 0,
                    ]);

                    if (!empty($variationData['attributes'])) {
                        // attributes arrive as array of attribute_value_id values
                        $ids = collect($variationData['attributes'])->pluck('attribute_value_id')->filter()->toArray();
                        $variation->attributeValues()->sync($ids);
                    }
                }
            }

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Product created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Product Store] Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
            'id'    => $product->id,
            'name'  => $product->name,
            'code'  => $product->item_code ?? '',
            'unit'  => $product->unit ?? '',
            'price' => $product->price ?? 0,
        ]);
    }

    public function edit($id)
    {
        $product    = Product::with(['images', 'variations.attributeValues'])->findOrFail($id);
        $categories = ProductCategory::all();
        $attributes = Attribute::with('values')->get();
        $units      = MeasurementUnit::all();
        $vendors    = ChartOfAccounts::whereIn('account_type', ['customer', 'vendor'])->orderBy('name')->get();

        $attributeValues = collect();
        foreach ($attributes as $attribute) {
            foreach ($attribute->values as $val) {
                $val->attribute = $attribute;
                $attributeValues->push($val);
            }
        }

        return view('products.edit', compact(
            'product', 'categories', 'attributes', 'attributeValues', 'units', 'vendors'
        ));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::findOrFail($id);

            $product->update($request->only([
                'name', 'category_id', 'vendor_id', 'sku', 'measurement_unit', 'item_type', // ← vendor_id
                'manufacturing_cost', 'opening_stock', 'description', 'selling_price',
                'consumption', 'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active',
            ]));

            $handledVariationIds = [];

            if (is_array($request->variations)) {
                foreach ($request->variations as $variationData) {
                    $variation = ProductVariation::findOrFail($variationData['id']);
                    $variation->update([
                        'sku'                => $variationData['sku'],
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity'     => $variationData['stock_quantity'] ?? 0,
                        'selling_price'      => $variationData['selling_price'] ?? 0,
                    ]);

                    if (!empty($variationData['attributes'])) {
                        $variation->attributeValues()->sync($variationData['attributes']);
                    }

                    $handledVariationIds[] = $variation->id;
                }
            }

            if (is_array($request->new_variations)) {
                foreach ($request->new_variations as $newVar) {
                    $variation = $product->variations()->create([
                        'sku'                => $newVar['sku'],
                        'manufacturing_cost' => $newVar['manufacturing_cost'] ?? 0,
                        'stock_quantity'     => $newVar['stock_quantity'] ?? 0,
                        'selling_price'      => $newVar['selling_price'] ?? 0,
                    ]);

                    if (!empty($newVar['attributes'])) {
                        $variation->attributeValues()->sync($newVar['attributes']);
                    }

                    $handledVariationIds[] = $variation->id;
                }
            }

            if ($request->filled('removed_variations')) {
                ProductVariation::whereIn('id', $request->removed_variations)->delete();
            }

            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $file) {
                    $path = $file->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);
                }
            }

            if ($request->filled('removed_images')) {
                foreach ($request->removed_images as $imgId) {
                    $img = $product->images()->find($imgId);
                    if ($img) {
                        if (Storage::disk('public')->exists($img->image_path)) {
                            Storage::disk('public')->delete($img->image_path);
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
            $variation = ProductVariation::with('product')->where('barcode', $barcode)->first();

            if ($variation) {
                return response()->json([
                    'success'   => true,
                    'type'      => 'variation',
                    'variation' => [
                        'id'         => $variation->id,
                        'product_id' => $variation->product_id,
                        'sku'        => $variation->sku,
                        'barcode'    => $variation->barcode,
                        'name'       => $variation->product->name,
                        'm.cost'     => $variation->product->manufacturing_cost,
                    ],
                ]);
            }

            $product = Product::where('barcode', $barcode)->first();

            if ($product) {
                return response()->json([
                    'success' => true,
                    'type'    => 'product',
                    'product' => [
                        'id'      => $product->id,
                        'name'    => $product->name,
                        'barcode' => $product->barcode,
                        'sku'     => $product->sku,
                        'm.cost'  => $product->manufacturing_cost,
                    ],
                ]);
            }

            return response()->json(['success' => false, 'message' => 'No product or variation found for this barcode']);

        } catch (\Exception $e) {
            Log::error('Barcode lookup failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error occurred while searching barcode']);
        }
    }

    public function getVariations($productId)
    {
        $product = Product::with('variations', 'measurementUnit')->find($productId);

        if (!$product) {
            return response()->json(['success' => false, 'variation' => []]);
        }

        $unitId     = $product->measurementUnit->id ?? null;
        $variations = $product->variations->map(fn($v) => [
            'id'   => $v->id,
            'sku'  => $v->sku,
            'unit' => $unitId,
        ])->toArray();

        return response()->json([
            'success'   => true,
            'variation' => $variations,
            'product'   => [
                'id'                 => $product->id,
                'name'               => $product->name,
                'manufacturing_cost' => $product->manufacturing_cost,
                'unit'               => $unitId,
            ],
        ]);
    }

    public function getVariations2($productId)
    {
        $product = Product::with([
            'variations.attributeValues.attribute',
            'measurementUnit',
        ])->find($productId);

        if (!$product) {
            return response()->json(['success' => false, 'variation' => []]);
        }

        $unitId     = $product->measurementUnit->id ?? null;
        $variations = $product->variations->map(fn($v) => [
            'id'         => $v->id,
            'sku'        => $v->sku,
            'unit'       => $unitId,
            'attributes' => $v->attributeValues->map(fn($av) => [
                'id'        => $av->id,
                'value'     => $av->value,
                'attribute' => ['id' => $av->attribute->id, 'name' => $av->attribute->name],
            ])->toArray(),
        ])->toArray();

        return response()->json([
            'success'   => true,
            'variation' => $variations,
            'product'   => [
                'id'                 => $product->id,
                'name'               => $product->name,
                'manufacturing_cost' => $product->manufacturing_cost,
                'unit'               => $unitId,
            ],
        ]);
    }

    /* ================================================================
     *  BULK EXPORT
     *  Columns: Product SKU | Product Name | Category ID | Unit ID |
     *           Item Type | Description | Vendor ID |
     *           Manufacturing Cost | Selling Price | Opening Stock |
     *           Reorder Level | Max Stock Level | Min Order Qty |
     *           Variation SKU | Variation Barcode | Variation Price |
     *           Variation Stock | ...attribute columns...
     * ================================================================ */
    public function bulkExport()
    {
        $attributes = Attribute::pluck('name')->toArray();

        // Fixed product-level columns + variation columns + dynamic attribute columns
        $columns = array_merge([
            'Product SKU',
            'Product Name',
            'Category ID',
            'Unit ID',
            'Item Type',
            'Description',
            'Vendor ID',
            'Manufacturing Cost',
            'Selling Price',
            'Opening Stock',
            'Reorder Level',
            'Max Stock Level',
            'Min Order Qty',
            'Variation SKU',
            'Variation Barcode',
            'Variation Price',
            'Variation Stock',
        ], $attributes);

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=products_export.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($columns, $attributes) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $products = Product::with(['variations.attributeValues.attribute'])->get();

            foreach ($products as $product) {
                // Shared product-level data
                $productRow = [
                    $product->sku,
                    $product->name,
                    $product->category_id,
                    $product->measurement_unit,
                    $product->item_type,
                    $product->description,
                    $product->vendor_id ?? '',          // ← vendor
                    $product->manufacturing_cost ?? 0,
                    $product->selling_price ?? 0,
                    $product->opening_stock ?? 0,
                    $product->reorder_level ?? 0,
                    $product->max_stock_level ?? 0,
                    $product->minimum_order_qty ?? 0,
                ];

                if ($product->variations->isEmpty()) {
                    // No variations – write one row with blank variation fields
                    $row = array_merge(
                        $productRow,
                        ['', '', 0, 0], // variation columns
                        array_fill(0, count($attributes), '') // attribute columns
                    );
                    fputcsv($file, $row);
                } else {
                    foreach ($product->variations as $variation) {
                        $variationRow = [
                            $variation->sku,
                            $variation->barcode ?? '',
                            $variation->selling_price ?? 0,
                            $variation->stock_quantity ?? 0,
                        ];

                        $attrRow = [];
                        foreach ($attributes as $attr) {
                            $match      = $variation->attributeValues->first(fn($av) => strtolower($av->attribute->name ?? '') === strtolower($attr));
                            $attrRow[]  = $match ? $match->value : '';
                        }

                        fputcsv($file, array_merge($productRow, $variationRow, $attrRow));
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /* ================================================================
     *  BULK IMPORT
     *  – Safe array_combine (pads/trims row to header length)
     *  – Products without variations: skips variation creation when
     *    Variation SKU is blank
     *  – Handles all new product-level columns
     * ================================================================ */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'file'           => 'required|mimes:xlsx,csv,txt',
            'delete_missing' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $rows = Excel::toArray([], $request->file('file'))[0] ?? [];
            if (empty($rows)) {
                throw new \Exception('Uploaded file is empty.');
            }

            // Normalise header: lowercase, trim whitespace
            $rawHeader = array_shift($rows);
            $header    = array_map(fn($h) => strtolower(trim((string)$h)), $rawHeader);
            $colCount  = count($header);

            $dbAttributes = Attribute::all()->keyBy(fn($a) => strtolower($a->name));

            $importedProductSKUs   = [];
            $importedVariationSKUs = [];

            foreach ($rows as $row) {
                // Skip completely blank rows
                $rowValues = array_filter(array_map('trim', array_map('strval', $row)));
                if (empty($rowValues)) {
                    continue;
                }

                // Safe combine: pad or trim row to match header length
                $row     = array_map('strval', $row);
                $rowPad  = array_pad(array_slice($row, 0, $colCount), $colCount, '');
                $rowData = array_combine($header, $rowPad);

                $productSku  = trim($rowData['product sku'] ?? '');
                $variationSku = trim($rowData['variation sku'] ?? '');

                if (empty($productSku)) {
                    continue; // nothing useful in this row
                }

                $importedProductSKUs[] = $productSku;

                // ── Upsert Product ──────────────────────────────────────
                $product = Product::updateOrCreate(
                    ['sku' => $productSku],
                    [
                        'name'               => trim($rowData['product name'] ?? ''),
                        'category_id'        => (int)($rowData['category id'] ?? 0) ?: null,
                        'measurement_unit'   => (int)($rowData['unit id'] ?? 0) ?: null,
                        'item_type'          => trim($rowData['item type'] ?? 'fg'),
                        'description'        => $rowData['description'] ?? null,
                        'vendor_id'          => ($rowData['vendor id'] ?? '') !== '' ? (int)$rowData['vendor id'] : null, // ← vendor
                        'manufacturing_cost' => (float)($rowData['manufacturing cost'] ?? 0),
                        'selling_price'      => (float)($rowData['selling price'] ?? 0),
                        'opening_stock'      => (float)($rowData['opening stock'] ?? 0),
                        'reorder_level'      => (float)($rowData['reorder level'] ?? 0),
                        'max_stock_level'    => (float)($rowData['max stock level'] ?? 0),
                        'minimum_order_qty'  => (float)($rowData['min order qty'] ?? 0),
                    ]
                );

                // ── Upsert Variation (only when SKU present) ────────────
                if ($variationSku !== '') {
                    $importedVariationSKUs[] = $variationSku;

                    $variation = ProductVariation::updateOrCreate(
                        ['sku' => $variationSku],
                        [
                            'product_id'    => $product->id,
                            'barcode'       => $rowData['variation barcode'] ?? null,
                            'selling_price' => (float)($rowData['variation price'] ?? 0),
                            'stock_quantity'=> (float)($rowData['variation stock'] ?? 0),
                        ]
                    );

                    // Sync attribute values
                    $syncIds = [];
                    foreach ($dbAttributes as $attrKey => $attribute) {
                        $value = trim($rowData[$attrKey] ?? '');
                        if ($value === '') {
                            continue;
                        }
                        $attrValue = AttributeValue::firstOrCreate([
                            'attribute_id' => $attribute->id,
                            'value'        => ucfirst(strtolower($value)),
                        ]);
                        $syncIds[] = $attrValue->id;
                    }

                    $variation->attributeValues()->sync($syncIds);
                }
            }

            // ── Optionally delete records not present in file ───────────
            if ($request->boolean('delete_missing')) {
                if (!empty($importedVariationSKUs)) {
                    ProductVariation::whereNotIn('sku', $importedVariationSKUs)->delete();
                }
                if (!empty($importedProductSKUs)) {
                    Product::whereNotIn('sku', $importedProductSKUs)->delete();
                }
            }

            DB::commit();

            $deleted = $request->boolean('delete_missing') ? 'Yes' : 'No';
            return back()->with('success', "Products imported successfully. Deleted missing: {$deleted}.");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Bulk Import] Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Bulk import failed: ' . $e->getMessage());
        }
    }

    /* ================================================================
     *  BULK UPLOAD TEMPLATE
     * ================================================================ */
    public function bulkUploadTemplate()
    {
        $attributes = Attribute::pluck('name')->toArray();
        $categories = ProductCategory::pluck('id', 'name')->toArray();
        $units      = MeasurementUnit::pluck('id', 'name')->toArray();

        $columns = array_merge([
            'Product SKU',
            'Product Name',
            'Category ID',
            'Unit ID',
            'Item Type',
            'Description',
            'Vendor ID',
            'Manufacturing Cost',
            'Selling Price',
            'Opening Stock',
            'Reorder Level',
            'Max Stock Level',
            'Min Order Qty',
            'Variation SKU',
            'Variation Barcode',
            'Variation Price',
            'Variation Stock',
        ], $attributes);

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=product_bulk_template.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($columns, $categories, $units) {
            $file    = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $catIds  = array_values($categories);
            $unitIds = array_values($units);

            // ── Finished goods with variations ──────────────────────────
            $finishedGoods = [
                ['CHR001', 'Office Chair',   'Ergonomic chair',         [['CHR001-B-M', '1234567890123', '5000', '20', 'Black', 'Medium'], ['CHR001-W-L', '1234567890124', '5200', '15', 'White', 'Large']]],
                ['DSK001', 'Wooden Desk',    'Solid oak office desk',   [['DSK001-B-STD', '2234567890123', '12000', '10', 'Brown', 'Standard'], ['DSK001-W-STD', '2234567890124', '12500', '8', 'White', 'Standard']]],
                ['LPT001', 'Laptop Table',   'Adjustable laptop table', [['LPT001-B-S', '3234567890123', '3000', '30', 'Black', 'Small'], ['LPT001-B-L', '3234567890124', '3500', '25', 'Black', 'Large']]],
            ];

            foreach ($finishedGoods as $fg) {
                foreach ($fg[3] as $variation) {
                    fputcsv($file, [
                        $fg[0], $fg[1], $catIds[0] ?? 1, $unitIds[0] ?? 1, 'fg', $fg[2],
                        '',     // Vendor ID (optional)
                        '1500', // Manufacturing Cost
                        $variation[2], // Selling Price
                        '0',    // Opening Stock
                        '5',    // Reorder Level
                        '100',  // Max Stock Level
                        '1',    // Min Order Qty
                        $variation[0], $variation[1], $variation[2], $variation[3],
                        $variation[4] ?? '',
                        $variation[5] ?? '',
                    ]);
                }
            }

            // ── Raw materials (no variations) ────────────────────────────
            $rawProducts = [
                ['RAW001', 'Wood Plank',   'Solid oak plank 2x4 feet'],
                ['RAW002', 'Steel Rod',    'High-strength steel rod 12mm'],
                ['RAW003', 'Foam Sheet',   'High density foam sheet'],
                ['RAW004', 'Leather Roll', 'Genuine brown leather roll'],
                ['RAW005', 'Glass Sheet',  'Tempered glass sheet 4x6'],
            ];

            foreach ($rawProducts as $raw) {
                fputcsv($file, [
                    $raw[0], $raw[1], $catIds[1] ?? 1, $unitIds[1] ?? 1, 'raw', $raw[2],
                    '',   // Vendor ID
                    '200', '0', '50', '10', '500', '1',
                    '',   // Variation SKU (blank = no variation)
                    '',   // Variation Barcode
                    '0',  // Variation Price
                    '0',  // Variation Stock
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}