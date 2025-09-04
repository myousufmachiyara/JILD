<?php

use App\Models\Product;
use App\Models\ProductVariation;

if (!function_exists('generateFgBarcode')) {
    function generateFgBarcode()
    {
        $lastProductFg = Product::where('item_type', 'fg')->max('id') ?? 0;
        $lastVariationFg = ProductVariation::max('id') ?? 0;

        $lastId = max($lastProductFg, $lastVariationFg) + 1;

        return 'FG-' . str_pad($lastId, 6, '0', STR_PAD_LEFT);
    }
}
