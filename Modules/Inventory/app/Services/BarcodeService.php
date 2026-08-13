<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;

class BarcodeService
{
    public function lookupByBarcode(string $barcode): ?Product
    {
        return Product::where('barcode', $barcode)->with(['category', 'stock'])->first();
    }

    public function lookupLocation(string $barcode): ?Location
    {
        return Location::where('code', $barcode)->with('warehouse')->first();
    }

    public function generateBarcode(Product $product): string
    {
        // Generate EAN-13 compatible code based on product id
        $base = str_pad((string) $product->id, 12, '0', STR_PAD_LEFT);

        // Calculate EAN-13 check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $base[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        return $base.$checkDigit;
    }
}
