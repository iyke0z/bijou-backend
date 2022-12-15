<?php

namespace App\Http\Controllers;

use App\Models\Banks;
use App\Models\BusinessDetails;
use App\Models\Expenditure;
use App\Models\PurchaseDetails;
use App\Models\Sales;
use App\Models\SplitPayments;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function general_report(Request $request){
        $start_date  = $request['start_date']; //start at 6:00am
        $end_date = $request['end_date']; //start at 4:am
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'platform' => 'required'
        ]);

        if($validated){
            if($request['platform'] != 'all'){
                $getTransactions = Transaction::whereBetween(\DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                    ->where('type', "!=", 'credit')
                                    ->where('type', "!=", 'new_acount')
                                    ->where('type', "!=", 'cancelled')
                                    ->where('platform', $request['platform'])
                                    ->with('customer')->with('user')
                                    ->with(['sales' => function($q){
                                        $q->join('products', 'sales.product_id', 'products.id');
                                    }])->get();
                $get_sales = Sales::join('transactions', 'sales.ref', 'transactions.id')
                ->whereBetween(\DB::raw('DATE(transactions.`created_at`)'), [$start_date, $end_date])
                ->where('transactions.status', '!=', 'cancelled')
                ->where('transactions.platform', $request['platform'])
                ->with('product')->with('user')->get();

            }else{
                $getTransactions = Transaction::whereBetween(\DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                    ->where('type', "!=", 'credit')
                                    ->where('type', "!=", 'new_acount')
                                    ->where('type', "!=", 'cancelled')
                                    ->with('customer')->with('user')
                                    ->with(['sales' => function($q){
                                        $q->join('products', 'sales.product_id', 'products.id');
                                    }])->get();
                $get_sales = Sales::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
                                    ->with('product')->with('user')->get();

            }
                $get_purchases = PurchaseDetails::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
                ->with('purchase')->with('product')->get();

                $get_expenditures = Expenditure::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
                ->with('type')->with('user')->get();

            $report = [
                'transaction' => $getTransactions,
                'sales' => $get_sales,
                'purchases' => $get_purchases,
                'expenditures' => $get_expenditures
            ];

            return res_success('report', $report);

        }

    }

    public function cancelled_receipt(Request $request){
        $start_date  = $request['start_date'];
        $end_date = $request['end_date'];
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if($validated){
            $getTransactions = Transaction::whereBetween(\DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                ->where('type', 'cancelled')
                                ->with('customer')
                                ->with(['sales' => function($q){
                                    $q->join('products', 'sales.product_id', 'products.id');
                                }])->get();

            $get_sales = Sales::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
            ->where('type', 'cancelled')
            ->with('product')
            ->with('user')->get();
            $report = [
                'transaction' => $getTransactions,
                'sales' => $get_sales,
            ];

            return res_success('report', $report);

        }

    }

    public function generate_sales_report(Request $request){
        $user = $request['user_id'];
        $start_date  = $request['start_date']; //start at 6:00am
        $end_date = $request['end_date']; //start at 4:am
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'user_id' => 'required'
        ]);

        if ($validated) {
            // get transactions between 6am the previous day - 4am today
            $transactions_today = Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->sum("amount");

            $transactions_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->sum("amount");

            // get sales between 6am the previous day and 4am today
            $sales_today = Sales::select(\DB::raw('sum(price * qty) as  amount'))->join('transactions', 'sales.ref', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(sales.created_at)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(sales.created_at,"%H:%i")'), ">=", "06:00")->get();
            $sales_today= $sales_today[0]['amount'];
            $sales_next_day = Sales::select(\DB::raw('sum(price * qty) as  amount'))->join('transactions', 'sales.ref', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE("sales.created_at")'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(sales.created_at,"%H:%i")'), "<=", "05:00")->get();
            $sales_next_day= $sales_next_day[0]['amount'];

            //get transactions paid with cash
            $cash_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "cash")->sum("amount");

            $cash_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "cash")->sum("amount");

            //get transactions paid with card
            $card_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "card")->sum("amount");

            $card_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "card")->sum("amount");

            //get transactions paid with transfer
            $transfer_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "transfer")->sum("amount");

            $transfer_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "transfer")->sum("amount");

             //get transactions paid with split
             $split_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "split")->sum("amount");

             $split_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "split")->sum("amount");

             $split_payment_card_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "card")->sum("split_payments.amount");
             $split_payment_card_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "card")->sum("split_payments.amount");
             $split_payment_cash_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "cash")->sum("split_payments.amount");
             $split_payment_cash_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "cash")->sum("split_payments.amount");
             $split_payment_transfer_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "transfer")->sum("split_payments.amount");
             $split_payment_transfer_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "transfer")->sum("split_payments.amount");

             //bank payment received
             $banks = Banks::get();
             $bank_sales = array();
             foreach ($banks as $bank) {
                $card__banktoday =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
                ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");

                $card_bank_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
                ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");

                $split_payment_bank_card_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $start_date)
                ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "card")->where('split_payments.bank_id', $bank['id'])->sum("split_payments.amount");
                $split_payment_bank_card_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(\DB::raw('DATE(split_payments.created_at)'),  $end_date)
                ->where(\DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "card")->where('split_payments.bank_id', $bank['id'])->sum("split_payments.amount");

                array_push($bank_sales, [
                    "bank_name" => $bank['name'],
                    'amount' => $card__banktoday +  $card_bank_next_day +  $split_payment_bank_card_today + $split_payment_bank_card_next
                ]);
             }

             // get comlementary
            $complementary_today = Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "complementary")->sum("amount");


            $complementary_next = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "complementary")->sum("amount");

            $oustanding = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)->where('user_id', $user)->with("sales")
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('status', 'pending')->with("split")->get();


            $summary = [
                "expected_amount" => $this->getVat($sales_today + $sales_next_day),
                "paid_amount" => $transactions_today + $transactions_next_day ,
                "cash" => $cash_today + $cash_next_day + $split_payment_cash_next + $split_payment_cash_today,
                "card" => $card_today + $card_next_day + $split_payment_card_next + $split_payment_card_today,
                "transfer" =>$transfer_today + $transfer_next_day + $split_payment_transfer_today + $split_payment_transfer_next,
                "split_payments" =>$split_today + $split_next_day,
                "split_payments_card" => $split_payment_card_today + $split_payment_card_next,
                "split_payments_transfer" => $split_payment_transfer_today + $split_payment_transfer_next,
                "split_payments_cash" => $split_payment_cash_today + $split_payment_cash_next,
                "banks" =>$bank_sales,
                "complementary" => $complementary_today + $complementary_next,
                "outsanding" => $oustanding
            ];

            return res_success('report', $summary);

        }
    }

    public function generate_report(Request $request){
        $start_date  = $request['start_date']; //start at 6:00am
        $end_date = $request['end_date']; //start at 4:am

        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

        if ($validated) {

            // get transactions between 6am the previous day - 4am today
            $transactions_today = Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")
            ->with('split')->with("user")->with('sales')->sum("amount");

            $transactions_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")
            ->with('split')->with("user")->with('sales')->sum("amount");

            // get sales between 6am the previous day and 4am today
            $sales_today = Sales::select(\DB::raw('sum(price * qty) as  amount'))->where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->get();
            $sales_today = $sales_today[0]['amount'];

            $sales_next_day = Sales::select(\DB::raw('sum(price * qty) as  amount'))->where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->get();
            $sales_next_day = $sales_next_day[0]['amount'];


            //get transactions paid with cash
            $cash_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "cash")->sum("amount");

            $cash_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "cash")->sum("amount");

            //get transactions paid with card
            $card_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "card")->sum("amount");

            $card_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "card")->sum("amount");

            //get transactions paid with transfer
            $transfer_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "transfer")->sum("amount");

            $transfer_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "transfer")->sum("amount");

             //get transactions paid with split
             $split_today =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "split")->sum("amount");

             $split_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "split")->sum("amount");

             $split_payment_card_today = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "card")->sum("amount");
             $split_payment_card_next = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "card")->sum("amount");
             $split_payment_cash_today = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "cash")->sum("amount");
             $split_payment_cash_next = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "cash")->sum("amount");
             $split_payment_transfer_today = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $start_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "transfer")->sum("amount");
             $split_payment_transfer_next = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $end_date)
             ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "transfer")->sum("amount");

             //bank payment received
             $banks = Banks::get();
             $bank_sales = array();
             foreach ($banks as $bank) {
                $card__banktoday =  Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
                ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");

                $card_bank_next_day = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
                ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");

                $split_payment_bank_card_today = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $start_date)
                ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");
                $split_payment_bank_card_next = SplitPayments::where(\DB::raw('DATE(`created_at`)'),  $end_date)
                ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");

                array_push($bank_sales, [
                    "bank_name" => $bank['name'],
                    'amount' => $card__banktoday +  $card_bank_next_day +  $split_payment_bank_card_today + $split_payment_bank_card_next
                ]);
             }
             // get comlementary
            $complementary_today = Transaction::where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("payment_method", "complementary")->sum("amount");

            $complementary_next = Transaction::where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where("payment_method", "complementary")->sum("amount");

            $oustanding_ = Transaction::with(["sales" => function($q){
                $q->with('product');
            }])->with("split")->with('user')->where(\DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where("status", "pending")->get();

            $oustanding__ = Transaction::with(["sales" => function($q){
                $q->with('product');
            }])->with("split")->with('user')->where(\DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(\DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('status', 'pending')->get();

            $oustanding = array_merge($oustanding_->toArray(), $oustanding__->toArray());

            $summary = [
                "expected_amount" => $this->getVat($sales_today + $sales_next_day),
                "paid_amount" => $transactions_today + $transactions_next_day ,
                "cash" => $cash_today + $cash_next_day + $split_payment_cash_next + $split_payment_cash_today,
                "card" => $card_today + $card_next_day + $split_payment_card_next + $split_payment_card_today,
                "transfer" =>$transfer_today + $transfer_next_day + $split_payment_transfer_today + $split_payment_transfer_next,
                "split_payments" =>$split_today + $split_next_day,
                "split_payments_card" => $split_payment_card_today + $split_payment_card_next,
                "split_payments_transfer" => $split_payment_transfer_today + $split_payment_transfer_next,
                "split_payments_cash" => $split_payment_cash_today + $split_payment_cash_next,
                "banks" =>$bank_sales,
                "complementary" => $complementary_today + $complementary_next,
                "oustanding" => $oustanding
            ];

            return res_success('report', $summary);

        }
    }

    public function getVat($val){
        $vat = BusinessDetails::first()->vat;
        $total = (doubleval($vat)/100) * ($val) + $val;

        return $total;
    }


}
