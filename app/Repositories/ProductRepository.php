<?php

namespace App\Repositories;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Category;
use App\Models\GeneralLedger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use App\Traits\ProductTrait;
use Illuminate\Support\Facades\Auth;

class ProductRepository implements ProductRepositoryInterface{
    use ProductTrait;

    public function create_category($request){
        $already_exists = [ ];
        $shopId = request()->query('shop_id');
        for ($i=0; $i < count($request['category']) ; $i++) {
            $check = Category::where('name', strtolower($request['category'][$i]['name']))->first();
            if (!$check) {
                // create category
                Category::create([
                    'name' => strtolower($request['category'][$i]['name']),
                    'has_stock' => $request['category'][$i]['has_stock'],
                    "shop_id" => $shopId
                    ]
                );
            }else{
                array_push($already_exists, $request['category'][$i]['name']);
            }
        }
        if(count($already_exists) > 0){
            return res_success('already exists', $already_exists);
        }
        return res_completed('categories added');
    }

    public function update_category($request, $id){
        $category = Category::find($id);
        if($category->exists()){
            // update
            $category->update(['name' => $request['name'],
                                    'has_stock' => $request['has_stock']
                            ]);
            return res_success('category updated', $category);
        }else{
            return res_not_found('category does not exist');
        }
    }

    public function delete_category($id){
        Category::findOrFail($id)->delete();
        return res_completed('deleted');
    }

    public function create_product($request){
        $already_exists = [];
        $shopId = request()->query('shop_id');
        for ($i=0; $i < count($request['products']) ; $i++) {
            $check = Product::where('name', strtolower($request['products'][$i]['name']))
                    ->where('category_id', $request['products'][$i]['category_id'])
                    ->where('code', $request['products'][$i]["code"])
                    ->first();
            if (!$check) {
                // create category
                Product::create(
                    ["name" => $request['products'][$i]["name"],
                    "category_id" => $request['products'][$i]["category_id"],
                    "stock" => $request['products'][$i]["stock"],
                    "price" => $request['products'][$i]["price"],
                    "code" => $request['products'][$i]["code"],
                    "shop_id" => $shopId ]
                );
            }else{
                array_push($already_exists, strtolower($request['products'][$i]['name']));
            }
        }
        if(count($already_exists) > 0){
            return res_success('already exists', $already_exists);
        }
        return res_completed('product added');
    }

    public function update_product($request, $id){
        $product = Product::find($id);
        $shopId = request()->query('shop_id');
        if($product->exists()){
            // checkifCodeExists
            $product_code = Product::where('code', $request['code'])
                            ->where('id', '!=', $id)->first();
            // if(!$product_code){
                // update
                $product->update(
                    [
                        'name' => $request['name'],
                        'category_id' => $request['category_id'],
                        'stock' => $request['stock'],
                        'price' => $request['price'],
                        'code' => $request['code']
                    ]);
                    $user = Auth::user()->id;
                    ProductTrait::log_product($id, 'update', $request['stock'], $request['price'], $user, $shopId);
                return res_success('product updated', $product);
            // }else{
            //     return res_bad_request('code exists');
            // }
        }else{
            return res_not_found('product does not exist');
        }
    }

    public function delete_product($id){
        Product::findOrFail($id)->delete();
        return res_completed('deleted');
    }

    public function generate_product_report($request, $id){
        $start_date = $request['start_date'];
        $end_date = $request['end_date'];
        $shopId = request()->query('shop_id');

        $report = applyShopFilter(Product::with('category')->with(
            ['sales' => function($q) use ($start_date, $end_date){
                $q->select('sales.*','users.fullname')
                ->join('users', 'sales.user_id', 'users.id')
                ->whereBetween('sales.created_at', [$start_date, $end_date]);
            }]
            )->with(
                ['purchases' => function($q) use ($start_date, $end_date) {
                    $q->select('purchase_details.*', 'purchases.user_id','users.fullname')
                        ->join('purchases', 'purchase_details.purchase_id', 'purchases.id')
                        ->join('users', 'purchases.user_id', 'users.id')
                        ->whereBetween('purchase_details.created_at', [$start_date, $end_date]);
                }]
            )->with('images'), $shopId)->find($id);
        return res_success('report', $report);
    }

    public function new_purchase($request){
        $totalPrice = [];
        $new_order = new Purchase();
        $new_order->user_id = Auth::user()->id;
        $new_order->save();
        $user = Auth::user()->id;
        $shopId = request()->query('shop_id');


        for ($i=0; $i < count($request['purchase']); $i++) {
            $previous_stock = applyShopFilter(Product::where('id', $request['purchase'][$i]["product_id"]), $shopId)->first();
            $purchase = PurchaseDetails::create([
                "purchase_id" => $new_order->id,
                "product_id" => $request['purchase'][$i]["product_id"],
                "previous_stock" => $previous_stock['stock'],
                "qty"=>$request['purchase'][$i]['qty'],
                "cost"=>$request['purchase'][$i]['cost'],
                "shop_id" => $shopId
            ]);
            ProductTrait::log_purchase($purchase->id, 'purchase', $request['purchase'][$i]['qty'], $request['purchase'][$i]['cost'], $user, $shopId);
            array_push($totalPrice, $request['purchase'][$i]['qty']*$request['purchase'][$i]['cost']);
            $purchase;
            // update product table
            $product = Product::find($request['purchase'][$i]["product_id"]);
            if($product->exists()){
                ProductTrait::log_product($product->id, 'purchase', $request['purchase'][$i]['qty'], $request['purchase'][$i]['cost'], $user, $shopId);
                $product->stock = $product->stock + $request['purchase'][$i]['qty'];
                // $product->price = $product->price + $request['purchase'][$i]['cost'];
                $product->out_of_stock = 0;
                $product->save();
            }

            
        }
        // getSumof "purchase_id" => $request['purchase'][$i]["purchase_id"],
        $price = array_sum($totalPrice);
        // update purchases
        $update_purchase = Purchase::find($new_order->id);
        $update_purchase->update([
            "added_costs"=>$request['added_cost'],
            "price" => $price
        ]);

       

        return res_completed('Purchase Saved');
    }

    public function update_purchase($request, $id){
        $totalPrice = [];
        $user = Auth::user()->id;
        $shopId = $request->query('shop_id');
        $products = [ ];
        for ($i=0; $i < count($request['purchase']); $i++) {
            // foreach of the object update if id exists and create new if not exists
            $single_purchase = PurchaseDetails::find($request['purchase'][$i]["id"]);
            if ($single_purchase->exists()) {
                // get product and remove old stock
                // update product table
                $product = Product::find($request['purchase'][$i]["product_id"]);
                if($product->exists()){
                    $product->stock = $product->stock  - $single_purchase->qty;
                    $product->out_of_stock = 0;
                    $product->save();
                }
                // log this purchase
                ProductTrait::log_purchase($single_purchase->id, 'update', $request['purchase'][$i]['qty'], $request['purchase'][$i]['cost'], $user, $shopId);
                //update purchase details with new information
                $detail = PurchaseDetails::find($single_purchase->id);
                $single_purchase->update([
                    "purchase_id" => $request['purchase'][$i]['purchase_id'],
                    "product_id" => $request['purchase'][$i]["product_id"],
                    "qty"=> $request['purchase'][$i]['qty'],
                    "cost"=> $request['purchase'][$i]['cost']
                ]);

                array_push($totalPrice, $request['purchase'][$i]['qty']*$request['purchase'][$i]['cost']);
                // get product and update stock
                $product = Product::find($request['purchase'][$i]["product_id"]);
                if($product->exists()){
                    // log product
                    ProductTrait::log_product($product->id, 'purchase_update', $request['purchase'][$i]['qty'], $request['purchase'][$i]['cost'], $user, $shopId);
                    $product->stock = $product->stock  + $request['purchase'][$i]['qty'];
                    // $product->price = $product->price + $request['purchase'][$i]['cost'];
                    $product->out_of_stock = 0;
                    $product->save();
                }


            }
        }
        
        // create new
        if(count($request['new_purchase']) > 0){
            for ($i=0; $i < count($request['new_purchase']); $i++) {
                PurchaseDetails::create([
                    "purchase_id" => $request['new_purchase'][$i]['purchase_id'],
                    "product_id" => $request['new_purchase'][$i]["product_id"],
                    "qty"=>$request['new_purchase'][$i]['qty'],
                    "cost"=>$request['new_purchase'][$i]['cost'],
                    'shop_id' => $shopId
                ]);
                // update product table
                $product = Product::find($request['new_purchase'][$i]["product_id"]);
                if($product->exists()){
                    $product->stock = $product->stock + $request['new_purchase'][$i]['qty'];
                    // $product->price = $product->price + $request['purchase'][$i]['cost'];
                    $product->out_of_stock = 0;
                    $product->save();
                }
                array_push($totalPrice, $request['new_purchase'][$i]['qty']*$request['new_purchase'][$i]['cost']);
            }
        }
        // getSumof "purchase_id" => $request['purchase'][$i]["purchase_id"],
        $price = array_sum($totalPrice);
        // update purchases
        $update_purchase = Purchase::find($id);
        $update_purchase->update([
            "added_costs"=>$request['added_cost'],
            "price" => $price,
        ]);

        return res_completed("purchase updated");
    }
    public function delete_purchase_detail($id){
        // reduce price from purchase
        $detail = PurchaseDetails::findOrFail($id);
        $purchase = Purchase::findOrFail($detail->purchase_id);
        $purchase->price = $purchase->price - $detail->cost * $detail->qty;
        $purchase->save();
        // getProduct and reduce stock
        // update product table
        $product = Product::find($detail->product_id);
        if($product->exists()){
            $product->stock = $product->stock  - $detail->qty;
            // $product->price = $product->price + $request['purchase'][$i]['cost'];
            $product->out_of_stock = 0;
            $product->save();
        }

         // delete from ledger
         $ledger = GeneralLedger::where('transaction_id', "purch_".$id)->get();
         $ledger = $ledger->toArray();
         for ($i= 0; $i < count($ledger) ; $i++) {
             GeneralLedger::findOrFail($ledger[$i]['id'])->delete();
         }
         
        $detail->delete();
        return res_completed("Deleted");
    }

    public function delete_purchase($id){
        Purchase::findOrFail($id)->delete();
        $detail = PurchaseDetails::where('purchase_id', $id)->get();
        $detail = $detail->toArray();
        for ($i=0; $i < count($detail) ; $i++) {
            PurchaseDetails::findOrFail($detail[$i]['id'])->delete();
        }

        // delete from ledger
        $ledger = GeneralLedger::where('transaction_id', "purch_".$id)->get();
        $ledger = $ledger->toArray();
        for ($i= 0; $i < count($ledger) ; $i++) {
            GeneralLedger::findOrFail($ledger[$i]['id'])->delete();
        }
        return res_completed("Deleted");
    }



}
