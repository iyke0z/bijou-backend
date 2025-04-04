<?php
namespace App\Traits;

use App\Models\ProductLog;
use App\Models\Product;
use App\Models\PurchaseDetails;
use App\Models\PurchaseLog;

trait ProductTrait{

    public static function log_product($productid, $action, $stock, $price, $user, $shopId){
        $product = Product::find($productid);
        if($product->exists()){
            // logprodcut
           ProductLog::create(
                [
                    "product_id" => $productid,
                    "action" => $action,
                    "old_price" => $product->price,
                    "new_price" => 0,
                    "old_stock" => $product->stock,
                    "new_stock" => $stock,
                    "user_id" => $user,
                    "shop_id" => $shopId
                ]
            );

        }
    }

    public static function log_purchase($detailid,$action, $stock, $price, $user, $shopId){
        $detail = PurchaseDetails::find($detailid);
        if($detail->exists()){
            // logpurchase
           PurchaseLog::create(
                [
                    "purchase_detail_id" => $detailid,
                    "action" => $action,
                    "old_price" => $detail->cost,
                    "new_price" => $price,
                    "old_stock" => $detail->qty,
                    "new_stock" => $stock,
                    "user_id" => $user,
                    "shop_id" => $shopId

                ]
            );
        }
    }
}
