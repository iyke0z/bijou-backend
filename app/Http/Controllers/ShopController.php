<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateShopRequest;
use App\Models\GeneralLedger;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccess;
use App\Models\ShopManager;
use App\Models\StrockTransaction;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function index(){
        $shops = Shop::all();

        return res_success("success", $shops);
    }
    public function create(CreateShopRequest $request){
            $validated = $request->validated();
           
            $newShop = Shop::create([
                "title" => $validated["title"],
                "address" => $validated["address"],
                "status" => $validated["status"],
                "contact_person" => $validated["contact_person"],
                "phone_number" => $validated["phone_number"],
            ]);
            
            return res_success("success", $newShop);


    }
    public function update(CreateShopRequest $request, $id){
        $validated = $request->validated();
        $shopExists = Shop::find($id);

        if ($shopExists) {
            $newShop = $shopExists->update([
                "title" => $validated["title"],
                "address" => $validated["address"],
                "status" => $validated["status"],
                "contact_person" => $validated["contact_person"],
                "phone_number" => $validated["phone_number"],
            ]);
            
            return res_success("success", $newShop);
        }else{
            return res_not_found('shop not found');
        }
    }
    public function show($id){
        $record = Shop::find($id);

        if ($record) {
            return res_success("success", $record);
        }else{
            return res_not_found('record not found');

        }
    }
    public function delete($id){
        $record = Shop::find($id)->delete();

        if ($record) {
            return res_success("success", "");
        }else{
            return res_not_found('record not found');

        }
    }

    public function assign(Request $request, $id){
        $addShops = $request['shopsToAdd'];
        $removeShops = $request['shopsToRemove'];
        //
        if(count($addShops) > 0){
            for ($i=0; $i < count($addShops); $i++) {
                $accessExists = ShopAccess::where('shop_id',$addShops[$i])->where('user_id', $id)->first();
                if (!$accessExists) {
                    $access = new ShopAccess();
                    $access->user_id = $id;
                    $access->shop_id = $addShops[$i];
                    $access->save();
                }
            }
        }

        if(count($removeShops) > 0){
            for ($i=0; $i < count($removeShops); $i++) {
                $exists = ShopAccess::where('shop_id',$removeShops[$i])->where('user_id', $id)->first();
                if ($exists) {
                    $exists->delete();
                }

            }
        }
        return res_completed('Assigned successfully');
    }

    public function transferProduct(Request $request){
        // foreach product if shop does not have the product, create new product
        $originatingShop = $request['originating_shop'];
        $destinationShop = $request['destination_shop'];
        $transferUserId = $request['shop_one_user_id'];
        $destinationUserId = $request['shop_two_user_id'];
       $transaction_status = $request['transaction_status'];
        $transaction_method = $request['transaction_method'];

        // foreach ($request['items'] as $key => $value) {
            // chceck if product in shop one exists in shop two
            $product = Product::where('id', $request['product_id'])->where('shop_id', $originatingShop)->first();
            $productExists = Product::where('name', $request['name'])->where('shop_id', $destinationShop)->first();
            if (!$productExists) {
                // create a new product
                Product::create([
                    "name" => $product['name'],
                    "category_id" => $product["category_id"],
                    "stock" => 0,
                    "price" => $product["price"],
                    "code" => $product["code"],
                    "shop_id" => $destinationShop 
                ]);
            }

            $qty = $request['qty'];
                $product_id = $request['product_id'];
                
                $transaction = StrockTransaction::create([
                                    "originating_shop" => $originatingShop,
                                    "destination_shop" => $destinationShop,
                                    "qty" => $qty,
                                    "product_id" => $product_id,
                                    "shop_one_user_id" => $transferUserId,
                                    "shop_two_user_id" => $destinationUserId,
                                    "previous_stock" => 0,
                                    "current_stock" => 0,
                                    "previous_stock_two" => 0,
                                    "current_stock_two"=> 0,
                                    "transaction_status" => $transaction_status,
                                    "transaction_method" => $transaction_method,
                                ]);


                $shop1 = Product::where('id', $transaction->product_id )->where('shop_id', $transaction->originating_shop)->first();
                $previous_stock = $shop1->stock;
                $current_stock = $previous_stock - $transaction->qty;
                $shop1->stock = $current_stock;
                $shop1->save();

                $transaction->previous_stock = $previous_stock;
                $transaction->current_stock = $current_stock;
                $transaction->save();
                // accounting
                $isNegativeStock = false;
                if ($shop1->current_stock < 0) {
                    $isNegativeStock = true;
                }

                registerLedger(
                    $isNegativeStock ? 'negative_stock' : 'sales',
                    "stktrf_".$transaction->id,
                    getCostPrice($shop1->id, $transaction['qty']),
                    $transaction->originating_shop,
                    'full_payment',
                    'transfer',
                    0,
                    $request['part_payment_amount'] ?? 0,
                    getCostPrice($shop1->is, $transaction['qty']) ,
                );
        // }

        return res_created('operation process started successfully', '');

    }

    public function recentTransfers($shopId){
        $transactions = StrockTransaction::with('product')
                        ->with('shopOne')
                        ->with('shopTwo')
                        ->where('originating_shop', $shopId)
                        ->orWhere('destination_shop', $shopId)
                        ->orderBy('created_at', 'DESC')->get();
        return res_success('success', $transactions);
    }
    public function approveTransfer($id){
        $transaction = StrockTransaction::find($id);
        $product_name = Product::where('id', $transaction->product_id )->first()->name;
        if ($transaction) {
            $shop2 = Product::where('name', $product_name )->where('shop_id', $transaction->destination_shop)->first();

            $previous_stock = $shop2->stock;
            $current_stock = $previous_stock + $transaction->qty;
            $shop2->stock = $current_stock;
            $shop2->save();

            $transaction->previous_stock_two = $previous_stock;
            $transaction->current_stock_two = $current_stock;
            $transaction->shop_two_user_id = Auth::user()->id;
            $transaction->save();

            $transaction->update([
                "transaction_status" => 'completed',
            ]);

            // accounting
            $isNegativeStock = false;
            if ($transaction->current_stock_two < 0) {
                $isNegativeStock = true;
            }

            registerLedger(
                $isNegativeStock ? 'negative_stock' : 'purchase',
                "stktrf_".$transaction->id,
                getCostPrice($shop2->id, $transaction['qty']),
                $transaction->destination_shop,
                'full_payment',
                'transfer',
                0,
                $request['part_payment_amount'] ?? 0,
                getCostPrice($shop2->id, $transaction['qty']) ,
            );
            // the receiving shop is purchasging the product from the originating shop

            // cost for this is same as the cost price of the product in the originating shop

            return res_success('success', $transaction);
        }else{
            return res_not_found('record not found');
        }
    }
    public function rejectTransfer($id){
        $transaction = StrockTransaction::find($id);
        if ($transaction) {
            $transaction->update([
                "transaction_status" => 'rejected',
                'shop_two_user_id' => Auth::user()->id

            ]);

            // return the product to shop 1
            $product = Product::where('id', $transaction->product_id )->where('shop_id', $transaction->originating_shop)->first();
            $previous_stock = $product->stock;
            $current_stock = $previous_stock + $transaction->qty;
            $product->stock = $current_stock;
            $product->save();

            // delete the accounting ledger
            $ledgers = GeneralLedger::where('transaction_id', "stktrf_".$transaction->id)->where('type', 'stktrf_'.$transaction->id)->get();
            return res_success('success', $transaction);
        }else{
            return res_not_found('record not found');
        }
    }
}
