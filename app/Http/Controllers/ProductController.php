<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
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
        $products   = Product::with('category', 'variations')->get();
        $categories = ProductCategory::all();
        return view('products.index', compact('products', 'categories'));
    }

    public function barcodeSelection()
    {
        $variations = ProductVariation::with('product')
            ->whereHas('product')
            ->get();
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
                $qty       = max(1, (int)($request->quantity[$variationId] ?? 1));
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
        $categories    = ProductCategory::all();
        $subcategories = ProductSubcategory::all();
        $attributes    = Attribute::with('values')->get();
        $units         = MeasurementUnit::all();
        $vendors       = ChartOfAccounts::whereIn('account_type', ['customer', 'vendor'])->orderBy('name')->get();

        return view('products.create', compact('categories', 'subcategories', 'attributes', 'units', 'vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string|max:255|unique:products,name',
            'category_id'        => 'required|exists:product_categories,id',
            'subcategory_id'     => 'nullable|exists:product_subcategories,id',
            'vendor_id'          => 'nullable|exists:chart_of_accounts,id',
            'sku'                => 'required|string|unique:products,sku',
            'barcode'            => 'nullable|string',
            'description'        => 'nullable|string',
            'measurement_unit'   => 'required|exists:measurement_units,id',
            'item_type'          => 'required|in:fg,raw,service',
            'manufacturing_cost' => 'nullable|numeric',
            'consumption'        => 'nullable|numeric',
            'selling_price'      => 'nullable|numeric',
            'opening_stock'      => 'required|numeric',
            'reorder_level'      => 'nullable|numeric',
            'max_stock_level'    => 'nullable|numeric',
            'minimum_order_qty'  => 'nullable|numeric',
            'is_active'          => 'boolean',
            'prod_att.*'         => 'nullable|image|mimes:jpeg,png,jpg,webp',
        ]);

        DB::beginTransaction();
        try {
            $productData = $request->only([
                'name', 'category_id', 'subcategory_id', 'vendor_id',
                'sku', 'barcode', 'description',
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
        $product         = Product::with(['images', 'variations.attributeValues'])->findOrFail($id);
        $categories      = ProductCategory::all();
        $subcategories   = ProductSubcategory::all();
        $attributes      = Attribute::with('values')->get();
        $units           = MeasurementUnit::all();
        $vendors         = ChartOfAccounts::whereIn('account_type', ['customer', 'vendor'])->orderBy('name')->get();

        $attributeValues = collect();
        foreach ($attributes as $attribute) {
            foreach ($attribute->values as $val) {
                $val->attribute = $attribute;
                $attributeValues->push($val);
            }
        }

        return view('products.edit', compact(
            'product', 'categories', 'subcategories', 'attributes', 'attributeValues', 'units', 'vendors'
        ));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $product = Product::findOrFail($id);

            $product->update($request->only([
                'name', 'category_id', 'subcategory_id', 'vendor_id',
                'sku', 'measurement_unit', 'item_type',
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

    // ================================================================
    //  BULK EXPORT
    // ================================================================
    public function bulkExport()
    {
        $attributes = Attribute::pluck('name')->toArray();

        $columns = array_merge([
            'Product SKU',
            'Product Name',
            'Category ID',
            'Subcategory ID',
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
            $file     = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $products = Product::with(['variations.attributeValues.attribute'])->get();

            foreach ($products as $product) {
                $productRow = [
                    $product->sku,
                    $product->name,
                    $product->category_id,
                    $product->subcategory_id ?? '',
                    $product->measurement_unit,
                    $product->item_type,
                    $product->description,
                    $product->vendor_id ?? '',
                    $product->manufacturing_cost ?? 0,
                    $product->selling_price ?? 0,
                    $product->opening_stock ?? 0,
                    $product->reorder_level ?? 0,
                    $product->max_stock_level ?? 0,
                    $product->minimum_order_qty ?? 0,
                ];

                if ($product->variations->isEmpty()) {
                    fputcsv($file, array_merge(
                        $productRow,
                        ['', '', 0, 0],
                        array_fill(0, count($attributes), '')
                    ));
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
                            $match     = $variation->attributeValues->first(
                                fn($av) => strtolower($av->attribute->name ?? '') === strtolower($attr)
                            );
                            $attrRow[] = $match ? $match->value : '';
                        }

                        fputcsv($file, array_merge($productRow, $variationRow, $attrRow));
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ================================================================
    //  BULK IMPORT
    // ================================================================
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

            // ── Normalise header ──────────────────────────────────────────
            $rawHeader = array_shift($rows);
            $header    = array_map(fn($h) => strtolower(trim((string)$h)), $rawHeader);
            $colCount  = count($header);

            // Skip helper/hint row (starts with "←")
            if (!empty($rows) && str_starts_with(trim((string)($rows[0][0] ?? '')), '←')) {
                array_shift($rows);
            }

            // ── Pre-load lookups ──────────────────────────────────────────
            $dbAttributes = Attribute::orderBy('id')->get()->keyBy(fn($a) => strtolower($a->name));

            $categoryMap = ProductCategory::all()
                ->keyBy(fn($c) => strtolower(trim($c->name)));

            $subcategoryMap = ProductSubcategory::all()
                ->keyBy(fn($s) => strtolower(trim($s->name)));

            $defaultUnit = MeasurementUnit::first()?->id ?? 1;

            // ── Resolve category ──────────────────────────────────────────
            $resolveCategory = function (string $raw) use (&$categoryMap): ?int {
                $raw = trim($raw);
                if ($raw === '' || strtolower($raw) === 'nan') return null;
                if (str_starts_with(strtolower($raw), 'http')) return null;

                if (is_numeric($raw)) {
                    $id = (int) $raw;
                    return $id > 0 ? $id : null;
                }

                $key = strtolower($raw);
                if (!isset($categoryMap[$key])) {
                    $newCat = ProductCategory::create([
                        'name' => ucwords($raw),
                        'code' => \Illuminate\Support\Str::slug($raw),
                    ]);
                    $categoryMap[$key] = $newCat;
                    Log::info('[Bulk Import] Created category', ['name' => $raw, 'id' => $newCat->id]);
                }
                return $categoryMap[$key]->id;
            };

            $getFallbackCategoryId = function () use (&$categoryMap): int {
                if ($categoryMap->isNotEmpty()) return $categoryMap->first()->id;
                $cat = ProductCategory::create(['name' => 'Imported', 'code' => 'imported']);
                $categoryMap['imported'] = $cat;
                return $cat->id;
            };

            // ── Resolve subcategory ───────────────────────────────────────
            $resolveSubcategory = function (string $raw) use (&$subcategoryMap): ?int {
                $raw = trim($raw);
                if ($raw === '' || strtolower($raw) === 'nan') return null;

                if (is_numeric($raw)) {
                    $id = (int) $raw;
                    return $id > 0 ? $id : null;
                }

                $key = strtolower($raw);
                if (!isset($subcategoryMap[$key])) {
                    $newSub = ProductSubcategory::create(['name' => ucwords($raw)]);
                    $subcategoryMap[$key] = $newSub;
                    Log::info('[Bulk Import] Created subcategory', ['name' => $raw, 'id' => $newSub->id]);
                }
                return $subcategoryMap[$key]->id;
            };

            // ── First pass: normalise all rows ────────────────────────────
            $parsedRows  = [];
            $lastProduct = [];
            $seenVarSKUs = [];

            foreach ($rows as $row) {
                $rowValues = array_filter(array_map('trim', array_map('strval', $row)));
                if (empty($rowValues)) continue;

                $row    = array_map('strval', $row);
                $rowPad = array_pad(array_slice($row, 0, $colCount), $colCount, '');
                $data   = array_combine($header, $rowPad);

                $productSku = trim($data['product sku'] ?? '');
                if (str_starts_with($productSku, '←') || $productSku === '') continue;

                // Forward-fill product-level fields across variation rows
                $productName = trim($data['product name'] ?? '');
                $isNan       = strtolower($productName) === 'nan';

                if ($productName === '' || $isNan) {
                    if (isset($lastProduct[$productSku])) {
                        foreach ([
                            'product name', 'category id', 'subcategory id', 'unit id',
                            'item type', 'description', 'vendor id', 'manufacturing cost',
                            'selling price', 'opening stock', 'reorder level',
                            'max stock level', 'min order qty',
                        ] as $col) {
                            if (($data[$col] ?? '') === '' || strtolower($data[$col] ?? '') === 'nan') {
                                $data[$col] = $lastProduct[$productSku][$col] ?? '';
                            }
                        }
                    }
                } else {
                    $lastProduct[$productSku] = $data;
                }

                // Deduplicate variation SKUs for engraving pairs
                $variationSku = trim($data['variation sku'] ?? '');
                if ($variationSku !== '') {
                    if (isset($seenVarSKUs[$variationSku])) {
                        $engravingKey = strtolower('add engraving?');
                        $prevData     = &$seenVarSKUs[$variationSku]['data'];
                        $prevEng      = strtoupper(trim($prevData[$engravingKey] ?? ''));
                        $currEng      = strtoupper(trim($data[$engravingKey] ?? ''));

                        if ($prevEng !== '' && $currEng !== '') {
                            $prevData['variation sku'] = $variationSku . '-' . $prevEng;
                            $data['variation sku']     = $variationSku . '-' . $currEng;
                            Log::info('[Bulk Import] Deduplicated variation SKU', [
                                'original' => $variationSku,
                                'fixed_a'  => $prevData['variation sku'],
                                'fixed_b'  => $data['variation sku'],
                            ]);
                        }
                    } else {
                        $seenVarSKUs[$variationSku] = ['data' => &$data];
                    }
                }

                $parsedRows[] = $data;
            }

            // ── Counters ──────────────────────────────────────────────────
            $importedProductSKUs   = [];
            $importedVariationSKUs = [];
            $productsCreated       = 0;
            $productsUpdated       = 0;
            $productsFailed        = 0;
            $variationsCreated     = 0;
            $variationsUpdated     = 0;
            $variationsFailed      = 0;

            // ── Second pass: upsert products and variations ───────────────
            foreach ($parsedRows as $rowIndex => $rowData) {
                $productSku   = trim($rowData['product sku']   ?? '');
                $variationSku = trim($rowData['variation sku'] ?? '');

                if ($productSku === '') continue;

                $importedProductSKUs[] = $productSku;

                $categoryId    = $resolveCategory($rowData['category id'] ?? '');
                if ($categoryId === null) $categoryId = $getFallbackCategoryId();

                $subcategoryId = $resolveSubcategory($rowData['subcategory id'] ?? '');

                $rawUnitId = trim($rowData['unit id'] ?? '');
                $unitId    = is_numeric($rawUnitId) && (int)$rawUnitId > 0
                    ? (int) $rawUnitId
                    : $defaultUnit;

                // ── Upsert Product ────────────────────────────────────────
                try {
                    $productName = trim($rowData['product name'] ?? '');
                    if (strtolower($productName) === 'nan') $productName = '';

                    if ($productName !== '') {
                        $conflict = Product::where('name', $productName)
                            ->where('sku', '!=', $productSku)
                            ->exists();
                        if ($conflict) {
                            $productName .= ' [' . $productSku . ']';
                        }
                    }

                    $wasNew  = !Product::where('sku', $productSku)->exists();
                    $product = Product::updateOrCreate(
                        ['sku' => $productSku],
                        [
                            'name'               => $productName,
                            'category_id'        => $categoryId,
                            'subcategory_id'     => $subcategoryId,
                            'measurement_unit'   => $unitId,
                            'item_type'          => trim($rowData['item type'] ?? 'fg') ?: 'fg',
                            'description'        => trim($rowData['description'] ?? '') ?: null,
                            'vendor_id'          => is_numeric($rowData['vendor id'] ?? '') && (int)($rowData['vendor id']) > 0
                                                    ? (int)$rowData['vendor id'] : null,
                            'manufacturing_cost' => is_numeric($rowData['manufacturing cost'] ?? null) ? (float)$rowData['manufacturing cost'] : 0,
                            'selling_price'      => is_numeric($rowData['selling price']      ?? null) ? (float)$rowData['selling price']      : 0,
                            'opening_stock'      => is_numeric($rowData['opening stock']      ?? null) ? (float)$rowData['opening stock']      : 0,
                            'reorder_level'      => is_numeric($rowData['reorder level']      ?? null) ? (float)$rowData['reorder level']      : 0,
                            'max_stock_level'    => is_numeric($rowData['max stock level']    ?? null) ? (float)$rowData['max stock level']    : 0,
                            'minimum_order_qty'  => is_numeric($rowData['min order qty']      ?? null) ? (float)$rowData['min order qty']      : 1,
                        ]
                    );

                    $wasNew ? $productsCreated++ : $productsUpdated++;

                } catch (\Throwable $e) {
                    $productsFailed++;
                    Log::error('[Bulk Import] Product failed', [
                        'row'   => $rowIndex + 2,
                        'sku'   => $productSku,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                // ── Upsert Variation ──────────────────────────────────────
                if ($variationSku === '') continue;

                try {
                    $importedVariationSKUs[] = $variationSku;

                    $wasNew    = !ProductVariation::where('sku', $variationSku)->exists();
                    $variation = ProductVariation::updateOrCreate(
                        ['sku' => $variationSku],
                        [
                            'product_id'         => $product->id,
                            'barcode'            => trim($rowData['variation barcode'] ?? '') ?: null,
                            'selling_price'      => is_numeric($rowData['variation price'] ?? null) ? (float)$rowData['variation price'] : 0,
                            'stock_quantity'     => is_numeric($rowData['variation stock'] ?? null) ? (float)$rowData['variation stock'] : 0,
                            'manufacturing_cost' => is_numeric($rowData['manufacturing cost'] ?? null) ? (float)$rowData['manufacturing cost'] : 0,
                        ]
                    );

                    $syncIds = [];
                    foreach ($dbAttributes as $attrKey => $attribute) {
                        $value = trim($rowData[$attrKey] ?? '');
                        if ($value === '' || strtolower($value) === 'nan') continue;

                        $attrValue = AttributeValue::firstOrCreate(
                            ['attribute_id' => $attribute->id, 'value' => ucfirst(strtolower($value))]
                        );
                        $syncIds[] = $attrValue->id;
                    }
                    $variation->attributeValues()->sync($syncIds);

                    $wasNew ? $variationsCreated++ : $variationsUpdated++;

                } catch (\Throwable $e) {
                    $variationsFailed++;
                    Log::error('[Bulk Import] Variation failed', [
                        'row'           => $rowIndex + 2,
                        'variation_sku' => $variationSku,
                        'product_sku'   => $productSku,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }

            // ── Optionally delete missing ─────────────────────────────────
            if ($request->boolean('delete_missing')) {
                if (!empty($importedVariationSKUs)) {
                    ProductVariation::whereNotIn('sku', $importedVariationSKUs)->delete();
                }
                if (!empty($importedProductSKUs)) {
                    Product::whereNotIn('sku', $importedProductSKUs)->delete();
                }
            }

            DB::commit();

            $summary   = "Products: {$productsCreated} created, {$productsUpdated} updated"
                . ($productsFailed  > 0 ? ", {$productsFailed} failed"  : '')
                . " | Variations: {$variationsCreated} created, {$variationsUpdated} updated"
                . ($variationsFailed > 0 ? ", {$variationsFailed} failed" : '');
            $deleted   = $request->boolean('delete_missing') ? ' | Missing deleted.' : '';
            $flashType = ($productsFailed > 0 || $variationsFailed > 0) ? 'error' : 'success';

            Log::info('[Bulk Import] Complete', ['summary' => $summary]);

            return back()->with($flashType, "Import complete. {$summary}{$deleted}");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Bulk Import] Fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Bulk import failed: ' . $e->getMessage());
        }
    }

    // ================================================================
    //  BULK UPLOAD TEMPLATE
    // ================================================================
    public function bulkUploadTemplate()
    {
        $attributes    = Attribute::orderBy('id')->pluck('name')->toArray();
        $categories    = ProductCategory::orderBy('id')->get(['id', 'name']);
        $subcategories = ProductSubcategory::orderBy('id')->get(['id', 'name']);
        $units         = MeasurementUnit::orderBy('id')->get(['id', 'name', 'shortcode']);

        $fixedCols = [
            'Product SKU',
            'Product Name',
            'Category ID',
            'Subcategory ID',
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
        ];

        $columns = array_merge($fixedCols, $attributes);

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=product_bulk_template.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($columns, $attributes, $categories, $subcategories, $units) {
            $file = fopen('php://output', 'w');

            // Row 1: headers
            fputcsv($file, $columns);

            // Row 2: helper hints
            $catList  = $categories->map(fn($c) => $c->id . '=' . $c->name)->implode(' | ');
            $subList  = $subcategories->count()
                ? $subcategories->map(fn($s) => $s->id . '=' . $s->name)->implode(' | ')
                : 'optional — leave blank if none';
            $unitList = $units->map(fn($u) => $u->id . '=' . $u->shortcode)->implode(' | ');

            $helperRow = [
                '← your SKU',
                '← product name',
                $catList,
                $subList,
                $unitList,
                'fg | raw | service',
                'optional description',
                'leave blank',
                'cost price',
                'selling price',
                'opening qty',
                'reorder qty',
                'max qty',
                'min order qty',
                '← variation SKU (blank if no variations)',
                'barcode (optional)',
                'variation sell price',
                'variation stock qty',
            ];

            foreach ($attributes as $attr) {
                $helperRow[] = '← ' . $attr . ' value';
            }

            fputcsv($file, $helperRow);

            // Example data
            $defaultCatId  = $categories->first()?->id ?? 1;
            $defaultSubId  = '';
            $defaultUnitId = $units->first()?->id ?? 1;
            $blankAttrs    = array_fill(0, count($attributes), '');

            $attrIdx = array_flip(array_map('strtolower', $attributes));

            $makeAttrRow = function (array $vals) use ($attributes, $attrIdx): array {
                $row = array_fill(0, count($attributes), '');
                foreach ($vals as $attrName => $val) {
                    $key = strtolower($attrName);
                    if (isset($attrIdx[$key])) {
                        $row[$attrIdx[$key]] = $val;
                    }
                }
                return $row;
            };

            // Example 1: FG with size + color
            $fgExamples = [
                ['sku' => 'JKT-BLK-S', 'color' => 'Black', 'size' => 'S', 'stock' => 10, 'price' => 5000],
                ['sku' => 'JKT-BLK-M', 'color' => 'Black', 'size' => 'M', 'stock' => 15, 'price' => 5000],
                ['sku' => 'JKT-BLK-L', 'color' => 'Black', 'size' => 'L', 'stock' => 12, 'price' => 5000],
                ['sku' => 'JKT-BRN-S', 'color' => 'Brown', 'size' => 'S', 'stock' => 8,  'price' => 5200],
                ['sku' => 'JKT-BRN-M', 'color' => 'Brown', 'size' => 'M', 'stock' => 10, 'price' => 5200],
            ];

            foreach ($fgExamples as $v) {
                fputcsv($file, array_merge([
                    'JKT-001', 'Classic Leather Jacket',
                    $defaultCatId, $defaultSubId, $defaultUnitId,
                    'fg', 'Premium quality leather jacket', '', '2500',
                    $v['price'], '0', '5', '100', '1',
                    $v['sku'], '', $v['price'], $v['stock'],
                ], $makeAttrRow(['size' => $v['size'], 'color' => $v['color']])));
            }

            // Example 2: FG with engraving
            $engravingExamples = [
                ['sku' => 'WLT-BLK-NO',  'color' => 'Black', 'add engraving?' => 'No',  'stock' => 20, 'price' => 1800],
                ['sku' => 'WLT-BLK-YES', 'color' => 'Black', 'add engraving?' => 'Yes', 'stock' => 10, 'price' => 2100],
                ['sku' => 'WLT-BRN-NO',  'color' => 'Brown', 'add engraving?' => 'No',  'stock' => 18, 'price' => 1800],
                ['sku' => 'WLT-BRN-YES', 'color' => 'Brown', 'add engraving?' => 'Yes', 'stock' => 8,  'price' => 2100],
            ];

            foreach ($engravingExamples as $v) {
                fputcsv($file, array_merge([
                    'WLT-001', 'Leather Bifold Wallet',
                    $defaultCatId, $defaultSubId, $defaultUnitId,
                    'fg', 'Genuine leather wallet with optional engraving', '', '700',
                    $v['price'], '0', '5', '50', '1',
                    $v['sku'], '', $v['price'], $v['stock'],
                ], $makeAttrRow(['color' => $v['color'], 'add engraving?' => $v['add engraving?']])));
            }

            // Example 3: FG no variations
            fputcsv($file, array_merge([
                'BELT-001', 'Classic Leather Belt',
                $defaultCatId, $defaultSubId, $defaultUnitId,
                'fg', 'Hand-stitched genuine leather belt', '', '400',
                '1200', '25', '5', '100', '1',
                '', '', '0', '0',
            ], $blankAttrs));

            // Example 4: Raw material
            $rawUnit = $units->firstWhere('shortcode', 'sq.ft')?->id
                    ?? $units->skip(1)->first()?->id
                    ?? $defaultUnitId;

            fputcsv($file, array_merge([
                'RAW-LEA-001', 'Genuine Sheep Leather',
                $defaultCatId, $defaultSubId, $rawUnit,
                'raw', 'Raw sheep leather for jacket production', '', '150',
                '0', '200', '20', '1000', '1',
                '', '', '0', '0',
            ], $blankAttrs));

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}