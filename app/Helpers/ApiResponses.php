<?php

use App\Models\BusinessTime;
use App\Models\GeneralLedger;
use App\Models\Liquidity;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use Illuminate\Support\Facades\Log;

if (!function_exists('applyShopFilter')) {
    function applyShopFilter($query, $shopId)
        {
            if (!is_null($shopId) && $shopId != 0) {
                return $query->where('shop_id', $shopId);
            }
            return $query;
        }
}

if (!function_exists('functionalities')) {
    function functionalities(){
        $functionalities = [
            [
                "id",
                "name"
            ]
        ];
        return $functionalities;
    }
}

if (!function_exists('res_auth_success')) {
    function res_auth_success($message, $data, $auth_token)
    {
        return response()->json([
            'status' => 'success',
            'status_code' => '000',
            'message' => $message,
            'auth_token' => $auth_token,
            'data' => $data
        ], 200);
    }
}


if (!function_exists('res_success')) {
    function res_success($message, $data)
    {
        return response()->json([
            'status' => 'success',
            'status_code' => '000',
            'message' => $message,
            'data' => $data
        ], 200);
    }
}

if (!function_exists('res_created')) {
    function res_created($message, $data)
    {
        return response()->json([
            'status' => 'created',
            'status_code' => '001',
            'message' => $message,
            'data' => $data
        ], 201);
    }
}

if (!function_exists('res_completed')) {
    function res_completed($message)
    {
        return response()->json([
            'status' => 'success',
            'status_code' => '017',
            'message' => $message,
        ], 200);
    }
}

if (!function_exists('res_phone_number_verified')) {
    function res_phone_number_verified($message)
    {
        return response()->json([
            'status' => 'success',
            'status_code' => '018',
            'message' => $message,
        ], 200);
    }
}


if (!function_exists('res_user_registered')) {
    function res_user_registered($message)
    {
        return response()->json([
            'status' => 'success',
            'status_code' => '019',
            'message' => $message,
        ], 200);
    }
}

if (!function_exists('res_bad_request')) {
    function res_bad_request($message)
    {
        return response()->json([
            'status' => 'created',
            'status_code' => '011',
            'message' => $message,
            'error' => 'Bad Request.'
        ], 400);
    }
}

if (!function_exists('res_new_otp_sent')) {
    function res_new_otp_sent($message)
    {
        return response()->json([
            'status' => 'success',
            'status_code' => '020',
            'message' => $message,
        ], 200);
    }
}

if (!function_exists('res_unauthorized')) {
    function res_unauthorized($message)
    {
        return response()->json([
            'status' => 'error',
            'status_code' => '012',
            'message' => $message,
            'error' => 'Unauthorized.'
        ], 401);
    }
}

if (!function_exists('res_not_found')) {
    function res_not_found($message)
    {
        return response()->json([
            'status' => 'error',
            'status_code' => '013',
            'message' => $message,
            'error' => 'Not Found.'
        ], 404);
    }
}

if (!function_exists('res_token_mismatch')) {
    function res_token_mismatch($message)
    {
        return response()->json([
            'status' => 'error',
            'status_code' => '014',
            'message' => $message,
            'error' => 'Token Mismatch.'
        ], 419);
    }
}

if (!function_exists('res_unprocess_entity')) {
    function res_unprocess_entity($message)
    {
        return response()->json([
            'status' => 'error',
            'status_code' => '015',
            'message' => $message,
            'error' => 'Unproccessable Entity.'
        ], 422);
    }
}

if (!function_exists('res_wrong_value')) {
    function res_wrong_value($message)
    {
        return response()->json([
            'status' => 'error',
            'status_code' => '016',
            'message' => $message,
            'error' => 'Wrong value provided.'
        ], 400);
    }
}

if (!function_exists('res_general_error')) {
    function res_general_error($message, $code)
    {
        return response()->json([
            'status' => 'error',
            'status_code' => '050',
            'message' => $message,
        ], $code);
    }
}

if (!function_exists('getBusinessTime')) {
    function getBusinessTime()
    {
        $business_time = BusinessTime::first();
        return $business_time;
    }
}


if (!function_exists('bankService')) {
    function bankService($transaction_amount, $remark, $transaction_reference, $shopId, $transaction_type)
    {
        $liquidityPreviousBalance = Liquidity::where('shop_id', $shopId)->latest()->first();

       
        
        if ($liquidityPreviousBalance) {
            Liquidity::create(
                [
                    "previous_balance" => $liquidityPreviousBalance->current_balance, 
                    "current_balance" => $transaction_type == "CREDIT" ? $transaction_amount + $liquidityPreviousBalance->current_balance : $liquidityPreviousBalance->current_balance -  $transaction_amount  , 
                    "transaction_amount" => $transaction_amount, 
                    "remark" => $remark, 
                    "transaction_reference" => $transaction_reference, 
                    "shop_id" => $shopId,
                    "transaction_type" => $transaction_type
                ]
            );
        }else{
                Liquidity::create(
                    [
                        "previous_balance" => 0, 
                        "current_balance" => $transaction_type == "CREDIT" ? 0 + $transaction_amount: 0 -  $transaction_amount  , 
                        "transaction_amount" => $transaction_amount, 
                        "remark" => $remark, 
                        "transaction_reference" => $transaction_reference, 
                        "shop_id" => $shopId,
                        "transaction_type" => $transaction_type
                    ]
                );
        }
        
    }

        if (!function_exists('getCostPrice')) {
            function getCostPrice($product_id){
                $costPrice = PurchaseDetails::where('product_id', $product_id)->orderBy('created_at', 'desc' )->first();
                return $costPrice->qty * $costPrice->cost;
            }
        }
        if (!function_exists('registerLedger')) {
            function registerLedger($activity, $transaction_id, $amount, $shopId, $payment_type, $payment_method = null, $logistics = 0, $partial_payment = 0, $cost_price = null)
            {
                // Sales transaction handling
                if ($activity == 'sales') {
                    Log::info('Processing sales transaction');
            
                    switch ($payment_type) {
                        case 'full_payment':
                            if ($payment_method === 'cash') {
                                registerTransaction('cash', 'debit', $amount, $transaction_id, $shopId, 'Debit cash account for full sales payment');
                            } else {
                                registerTransaction('bank', 'debit', $amount, $transaction_id, $shopId, 'Debit bank account for full sales payment');
                            }
                            registerTransaction('sales', 'credit', $amount, $transaction_id, $shopId, 'Credit sales account for full payment');
                            
                            // Handle COGS and inventory on sale
                            if ($cost_price !== null) {
                                registerTransaction('cost_of_goods_sold', 'debit', $cost_price, $transaction_id, $shopId, 'Debit COGS for sold inventory');
                                registerTransaction('inventory', 'credit', $cost_price, $transaction_id, $shopId, 'Credit inventory for sold item');
                            }
                            break;
            
                        case 'on_credit':
                            registerTransaction('accounts_receivable', 'debit', $amount, $transaction_id, $shopId, 'Debit accounts receivable for credit sale');
                            registerTransaction('sales', 'credit', $amount, $transaction_id, $shopId, 'Credit sales account for credit sale');
                            
                            // Handle COGS and inventory on credit sale
                            if ($cost_price !== null) {
                                registerTransaction('cost_of_goods_sold', 'debit', $cost_price, $transaction_id, $shopId, 'Debit COGS for sold inventory');
                                registerTransaction('inventory', 'credit', $cost_price, $transaction_id, $shopId, 'Credit inventory for sold item');
                            }
                            break;
            
                        case 'part_payment':
                            $receivable = $amount - $partial_payment;
                            registerTransaction('accounts_receivable', 'debit', $receivable, $transaction_id, $shopId, 'Debit accounts receivable for part payment');
                            registerTransaction('sales', 'credit', $amount, $transaction_id, $shopId, 'Credit sales account for part payment');
            
                            if ($payment_method === 'cash') {
                                registerTransaction('cash', 'debit', $partial_payment, $transaction_id, $shopId, 'Debit cash account for part payment');
                            } else {
                                registerTransaction('bank', 'debit', $partial_payment, $transaction_id, $shopId, 'Debit bank account for part payment');
                            }
                            
                            // Handle COGS and inventory for part payment
                            if ($cost_price !== null) {
                                registerTransaction('cost_of_goods_sold', 'debit', $cost_price, $transaction_id, $shopId, 'Debit COGS for sold inventory');
                                registerTransaction('inventory', 'credit', $cost_price, $transaction_id, $shopId, 'Credit inventory for sold item');
                            }
                            break;
            
                        case 'complementary':
                            registerTransaction('complementary', 'debit', $amount, $transaction_id, $shopId, 'Debit complementary account');
                            registerTransaction('sales', 'credit', $amount, $transaction_id, $shopId, 'Credit sales account for complementary sale');
                            
                            // Handle COGS and inventory for complementary sale
                            if ($cost_price !== null) {
                                registerTransaction('cost_of_goods_sold', 'debit', $cost_price, $transaction_id, $shopId, 'Debit COGS for sold inventory');
                                registerTransaction('inventory', 'credit', $cost_price, $transaction_id, $shopId, 'Credit inventory for sold item');
                            }
                            break;
                    }
            
                    if ($logistics > 0) {
                        registerTransaction('logistics', 'credit', $logistics, $transaction_id, $shopId, 'Credit logistics account for delivery cost');
                        registerTransaction('sales', 'debit', $logistics, $transaction_id, $shopId, 'Debit sales account for logistics cost');
                    }
                }
            
                // Purchase transaction handling
                if ($activity == 'purchase') {
                    Log::info('Processing purchase transaction');
            
                    switch ($payment_type) {
                        case 'full_payment':
                            if ($payment_method === 'cash') {
                                registerTransaction('cash', 'credit', $amount, $transaction_id, $shopId, 'Credit cash account for purchase');
                            } else {
                                registerTransaction('bank', 'credit', $amount, $transaction_id, $shopId, 'Credit bank account for purchase');
                            }
                            registerTransaction('purchase', 'debit', $amount, $transaction_id, $shopId, 'Debit purchase account for full payment');
            
                            // Handle inventory on purchase
                            if ($amount !== null) {
                                registerTransaction('inventory', 'debit', $amount, $transaction_id, $shopId, 'Debit inventory account for purchase');
                                registerTransaction('cost_of_purchase', 'credit', $amount, $transaction_id, $shopId, 'Credit cost of purchase for inventory acquisition');
                            }
                            break;
            
                        case 'on_credit':
                            registerTransaction('accounts_payable', 'credit', $amount, $transaction_id, $shopId, 'Credit accounts payable for credit purchase');
                            registerTransaction('purchase', 'debit', $amount, $transaction_id, $shopId, 'Debit purchase account for credit');
            
                            // Handle inventory on credit purchase
                            if ($amount !== null) {
                                registerTransaction('inventory', 'debit', $amount, $transaction_id, $shopId, 'Debit inventory account for purchase');
                                registerTransaction('cost_of_purchase', 'credit', $amount, $transaction_id, $shopId, 'Credit cost of purchase for inventory acquisition');
                            }
                            break;
            
                        case 'part_payment':
                            $payable = floatval($amount) - floatval($partial_payment);
                            registerTransaction('accounts_payable', 'credit', $payable, $transaction_id, $shopId, 'Credit accounts payable for part payment');
            
                            if ($payment_method === 'cash') {
                                registerTransaction('cash', 'credit', $partial_payment, $transaction_id, $shopId, 'Credit cash account for part payment');
                            } else {
                                registerTransaction('bank', 'credit', $partial_payment, $transaction_id, $shopId, 'Credit bank account for part payment');
                            }
                            registerTransaction('purchase', 'debit', $amount, $transaction_id, $shopId, 'Debit purchase account for part payment');
            
                            // Handle inventory on part payment purchase
                            if ($amount !== null) {
                                registerTransaction('inventory', 'debit', $amount, $transaction_id, $shopId, 'Debit inventory account for purchase');
                                registerTransaction('cost_of_purchase', 'credit', $amount, $transaction_id, $shopId, 'Credit cost of purchase for inventory acquisition');
                            }
                            break;
                    }
                }
            
                // Negative stock handling
                if ($activity == 'negative_stock') {
                    registerTransaction('stock_adjustment', 'credit', $amount, $transaction_id, $shopId, 'Credit stock adjustment account for negative inventory');
                    registerTransaction('inventory', 'debit', $amount, $transaction_id, $shopId, 'Debit inventory account for negative stock adjustment');
                }
            }
        }
    
    // Helper method to register transaction into general ledger
    
    function registerTransaction($account_name, $transaction_type, $amount, $transaction_id, $shopId, $description)
    {
        // Get the current balance of the account before updating
        $current_balance = getCurrentBalance($account_name, $shopId);
        Log::info(''. $transaction_type .''. $amount);
        
        // Calculate the new balance (for future use, might add logic to update balance)
        // Update balance logic can go here if needed
        
        // Insert the transaction record into the general ledger table
        GeneralLedger::create([
            'account_name' => $account_name,
            'transaction_type' => $transaction_type,
            'description' => $description,
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'shop_id' => $shopId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    // Method to get current balance of an account
    function getCurrentBalance($account_name, $shopId)
    {
        $latest_transaction = GeneralLedger::where('account_name', $account_name)
            ->where('shop_id', $shopId)
            ->orderBy('created_at', 'desc')
            ->first();
    
        return $latest_transaction ? $latest_transaction->current_balance : 0;  // Return the last known balance, or 0 if no previous transactions
    }
    


    // if (!function_exists('registerLedger')) {
    //     function registerLedger($activity, $activity_type=null, $amount, $transaction_id = null, $shopId){
    //         /*
    //             activities: sales, purchases

    //             SALES: 
    //                 if activity is sales, get the payment method, and payment type
    //                 if it is cash debit cash account and credit sales account, 
    //                 if it is transfer debit bank account and credit sales account, 
    //                 if it is card debit bank account and credit sales account, 
    //                 if it is wallet debit bank account and credit sales account, 
    //                 if it is on_credit debit accounts receivable and credit sales account, 
    //                 if it is pos debit bank account and credit sales account, 
    //                 if it is split_cash debit cash account and credit sales account, 
    //                 if it is split_transfer or pos debit bank account and credit sales account,
    //                 if it is payment_type complementary(no_paid for) debit complementary account and credit sales account, ignore payment method
    //                  if it is part_payment type, debit accounts receivable and credit sales account of the amount - part_payment_amount
    //                 if sales has logistics fee, credit logistics account and debit sales account of the logistics fee
    //                 if payment type is on_credit, ignore the payment method and debit accounts receivable and credit sales account of the amount - part_payment_amount
                
    //             PURCHASES:
    //                 if activity is purchases, get the expense type, get the payment method,
    //                 if it is logistics debit logistics account
    //                 if it is cogs debit cogs account
    //                 if it is salaries debit salaries account
    //                 if it is general opex debit general opex account
    //                 if it is marketing cost debit marketing cost account,
    //                 if it is utilities debit utilities account, 

    //                 for each purchase detail, get the payment method,
    //                 if it is cash credit cash account
    //                 if it is transfer credit bank account
    //                 if it is card credit bank account
    //                 if it is wallet credit bank account
    //                 if it is on_credit credit accounts payable
    //                 if it is part_payment credit accounts payable and debit cash account or bank account or wallet account  

    //         */ 
            
    //     }
    // }


}
