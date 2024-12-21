<?php

namespace App\Repositories;

use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Customer;
use App\Models\CustomerDiscount;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Sales;
use App\Models\SplitPayments;
use App\Models\Transaction;
use App\Models\WaiterCode;
use App\Traits\BusinessTrait;
use App\Traits\CheckoutTrait;
use App\Traits\CustomerTrait;
use App\Traits\ProductTrait;
use App\Traits\RefCode;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Auth;

class TransactionRepository implements TransactionRepositoryInterface{
    use ProductTrait, RefCode, CustomerTrait, BusinessTrait, CheckoutTrait;

    public function drinks_prep_status(){
        $drinks = Sales::with(['product' => function($q){
            $q->where('category_id',"!=", 1)->where('category_id', "!=", 2);
        }])->with(['transaction' => function($q) {
            $q->where('status', "!=", "cancelled");
        }])->with('user')->where('prep_status', "not_ready")->OrWhere('prep_status', "almost_ready")->latest()->get();

        return res_success('drinks', $drinks);

    }

    public function food_prep_status(){
        $food = Sales::with(['product' => function($q){
            $q->where('category_id', 1);
        }])->with(['transaction' => function($q) {
            $q->where('status', "!=", "cancelled");
        }])->with('user')->where('prep_status', "not_ready")->OrWhere('prep_status', "almost_ready")->latest()->get();
        return res_success('food', $food);
    }
    public function update_prep_status($request){
        $sales = Sales::find($request['id']);

        if($sales->exists()){
            $sales->update([
                "prep_status" => $request['prep_status']
            ]);

            return res_completed('status updated');
        }
        return res_not_found('order not found');
    }
    public function sell($request){
        $auth = WaiterCode::where('code', $request["auth_code"])->first();

        if($auth->exists()){
            // create new transaction
            $transaction = new Transaction();
            $transaction->platform = 'online';
            $transaction->user_id = $auth->user_id;
            $transaction->status =  $request['is_order'] == true ? "pending" : "completed";
            $request['is_order'] == false ? $transaction->type = "sold" : null;
            $request['is_order'] == false ? $transaction->amount  = $request['amount'] : null;
            $request['is_order'] == false ? $transaction->payment_method = $request['payment_method'] : null;
            $transaction->table_description = $request['description'];
            $transaction->customer_id = $request['customer_id'] ?? $request['customer_id'];
            $transaction->save();
            // create new sales order

            for ($i=0; $i < count($request['products']) ; $i++) {
                $product = Product::with('category')->where('id',$request['products'][$i]["product_id"])->first();
                if($product->category->has_stock == 1){
                    $product->stock = $product->stock - $request['products'][$i]["qty"];
                    $product->save();
                }

                $sale = new Sales();
                $sale->product_id = $request['products'][$i]["product_id"];
                $sale->ref = $transaction->id;
                $sale->price = $request['products'][$i]["price"];
                $sale->qty = $request['products'][$i]["qty"];
                $sale->user_id = $auth->user_id;
                $sale->save();
            }

            if($request['payment_method'] == "wallet" || $request['payment_method'] == 'on_credit'){
                $customer = Customer::find($request["customer_id"]);
                $customer->wallet_balance = $customer->wallet_balance - $request["amount"];
                $customer->save();
            }
            return res_success('sale order created', $sale);
        }

        return res_unauthorized('Unauthorized');

    }
    public function update_sale($request){
        $auth = WaiterCode::where('code', $request["auth_code"])->first();

        if($auth->exists()){
            // delete
            $sales = Sales::where('ref', $request['ref'])->get();
            foreach ($sales as $sale) {
                // return qty to Product
                $product = Product::where('id', $sale["product_id"])->first();
                if($product->category_id == 2){
                    $product->stock = $product->stock + $sale["qty"];
                    $product->save();
                }
            }

            // update
            $transaction = Transaction::find($request['ref']);
            $transaction->update(['table_description' => $request['description']]);
            for ($i=0; $i < count($request['products']) ; $i++) {
                $product = Product::where('id',$request['products'][$i]["product_id"])->first();
                if($product->category_id == 2){
                    $product->stock = $product->stock - $request['products'][$i]["qty"];
                    $product->save();
                }

                $sale = Sales::where("ref", $request['ref'])->where("product_id", $request['products'][$i]["product_id"])->first();
                if($sale) {
                    $sale->product_id = $request['products'][$i]["product_id"];
                    $sale->ref = $request['ref'];
                    $sale->price = $request['products'][$i]["price"];
                    $sale->qty = $request['products'][$i]["qty"];
                    $sale->user_id = $auth->user_id;
                    $sale->save();
                }else{
                    $sale = new Sales();
                    $sale->product_id = $request['products'][$i]["product_id"];
                    $sale->ref = $request['ref'];
                    $sale->price = $request['products'][$i]["price"];
                    $sale->qty = $request['products'][$i]["qty"];
                    $sale->user_id = $auth->user_id;
                    $sale->save();
                }
            }
            return res_completed('sale order updated');
        }
        return res_unauthorized('Unauthorized');
    }
    public function pay($request){
        $auth = WaiterCode::where('code', $request["auth_code"])->first();

        if($auth->exists()){
            // update transaction
            $transaction = Transaction::find($request["ref"]);
            $transaction->type = "sold";
            $transaction->amount = $request["amount"];
            $transaction->customer_id = $request["customer_id"];
            $transaction->payment_method = $request["payment_method"];
            $transaction->bank_id = $request["bank_id"];
            $transaction->status = 'completed';
            $transaction->save();

            if($request['payment_method'] == "split"){
                foreach ($request["split"] as $split) {
                    // store split values
                    $split_payment = new SplitPayments();
                    $split_payment->transaction_id = $transaction->id;
                    $split_payment->payment_method = $split["split_playment_method"];
                    $split_payment->amount = $split["split_payment_amount"];
                    $split_payment->bank_id = $split["bank_id"];
                    $split_payment->save();
                }
            }

            if($request['payment_method'] == "wallet" || $request['payment_method'] == 'on_credit'){
                $customer = Customer::find($request["customer_id"]);
                $customer->wallet_balance = $customer->wallet_balance - $request["amount"];
                $customer->save();
            }

            return res_completed("Payment Successful");
        }

        return res_unauthorized('Unauthorized');

    }

    public function delete_sale($request){
        //$auth = WaiterCode::where('code', $request["auth_code"])->first();

        //if($auth->exists()){//
            // delete
            $sales = Sales::where('ref', $request['ref'])->get();
            foreach ($sales as $sale) {
                // return qty to Product
                $product = Product::where('id', $sale["product_id"])->first();
                if($product->category_id == 2){
                    $product->stock = $product->stock + $sale["qty"];
                    $product->save();
                }

                $sale->delete();
            }

            // update
            $transaction = Transaction::find($request['ref']);
            $transaction->update(['status' => "cancelled"]);
            $transaction->delete();

            return res_completed('sale order cancelled');
        //}
        // return res_unauthorized('Unauthorized');

    }

    public function create_discount($request) {
        /* this function craetes a discount code */
        $check = Discount::where('code', $request['code'])->first();
        if(!$check){
            Discount::create([
            "code"=> $request["code"],
            "percentage" => $request["percentage"],
            "count" => $request["count"],
            "expiry_date" => $request["expiry_date"],
        ]);
            return res_completed('Discount Code Created');
        }else{
            return res_bad_request('This code already Exists');
        }

    }

    public function update_discount($request, $id){
        /* this function updates  discount code */
        $discount = Discount::find($id);
        if($discount->exists()){
            // update
            $discount->update([
                "code"=> $request["code"],
                "percentage" => $request["percentage"],
                "count" => $request["count"],
                "expiry_date" => $request["expiry_date"],
            ]);
            return res_completed('update successfull');
        }else{
            return res_not_found('discount code does not exist');
        }
    }

    public function delete_discount($id){
        $customers = CustomerDiscount::where('discount_id', $id)->get();
        $customers = $customers->toArray();
        for ($i=0; $i < count($customers) ; $i++) {
            CustomerDiscount::findOrFail($customers[$i]['id'])->delete();
        }
        Discount::findOrFail($id)->delete();
        return res_completed('Discount deleted');
    }

    public function customer_discount($request, $id){
        for ($i=0; $i < count($request["discounts"]) ; $i++) {
            $check = CustomerDiscount::where('customer_id', $id)
                    ->where('discount_id', $request["discounts"][$i]["discount"])->first();
            if(!$check){
                CustomerDiscount::create([
                    "customer_id" => $id,
                    "discount_id" => $request["discounts"][$i]["discount"],
                ]);
            }else{
                return res_completed("Discount Record Exists");
            }
        }
        return res_completed("Customer Assigned Discount");
    }

    public function delete_customer_discount($id, $discount){
        $customer = CustomerDiscount::where("customer_id", $id)
                    ->where('discount_id', $discount)->first();
        if($customer){
            CustomerDiscount::findOrFail($customer['id'])->delete();
        }
        return res_completed('Discount revoked');
    }
}
