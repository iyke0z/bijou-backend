<?php
namespace App\Traits;

use App\Models\Product;

trait CategoryTrait{
    public static function get_product_images($products) {
        $imgs = array();
        for ($i=0; $i < count($products) ; $i++) {
            $images = Product::where('id', $products[$i]->id)->with('images')->get();
            array_push($imgs, $images);
        }
        return $imgs;
    }
}
