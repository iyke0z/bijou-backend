<?php

namespace App\Repositories;

use App\Interfaces\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\Transaction;
use App\Traits\RefCode;
use Illuminate\Support\Facades\Auth;

class CustomerRepository implements CustomerRepositoryInterface{
    use RefCode;
    public function create_customer($request){
        $customer = Customer::create([
            "fullname"=>$request["fullname"],
            "address"=>$request["address"],
            "phone"=>$request["phone"],
            "email"=>$request["email"],
            "wallet_balance"=>$request["wallet_balance"]
        ]);
        $customer;
        // $transaction = new Transaction();
        // $transaction->type = "new_acount";
        // $transaction->amount = $request["wallet_balance"];
        // $transaction->customer_id = $customer->id;
        // $transaction->user_id = auth()->user()->id;
        // $transaction->save();
        return res_completed("account created");
    }

    public function update_customer($request, $id){
        $customer = Customer::find($id);
        if($customer->exists()){
            $customer->update([
                "fullname"=>$request["fullname"],
                "address"=>$request["address"],
                "phone"=>$request["phone"],
                "email"=>$request["email"],
            ]);
            return res_completed('Account updated');
        }
        return res_not_found('Customer not found');
    }

    public function fund_customer($request, $id){
        $customer = Customer::find($id);

        if($customer->exists()){
            if ($request['platform'] == 'online') {
                // payment gateway
                $this->fund_wallet($customer, $request, $id);
            }else{
                $this->fund_wallet($customer, $request, $id);
            }
            return res_completed('Account Credited Successfully');
        }
        return res_not_found('Account does not exist');
    }

    public function fund_wallet($customer, $request, $id){
            $shopId = request()->query('shop_id');
            $customer->wallet_balance = $customer->wallet_balance + $request['amount'];
            $customer->save();
            // log
            $transaction = new Transaction();
            $transaction->type = "credit";
            $transaction->amount = $request['amount'];
            $transaction->customer_id = $id;
            $transaction->user_id = Auth::user()->id;
            $transaction->shop_id = $shopId;
            // $transaction->payment_method = RefCode::gen_ref_code();
            $transaction->platform = $request['platform'] == 'offline' ? 'offline' : 'online';
            $transaction->save();

            bankService(
                $request['amount'], 
                "FUND CUSTOMER WALLET", 
                $transaction->id,
                $shopId,
                "CREDIT"
            );
    }

    public function delete_customer($id){
        Customer::findOrFail($id)->delete();
        return res_completed('deleted');
    }

    public function customer_details($id){
        $detail = Customer::with('transactions')->with([
            'discounts' => function ($q){
                $q->join('discounts', 'customer_discounts.discount_id', 'discounts.id');
            }
        ])->find($id);
        return res_success("details", $detail);
    }
}
