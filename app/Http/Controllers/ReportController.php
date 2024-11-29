<?php

namespace App\Http\Controllers;

use App\Models\Banks;
use App\Models\BusinessDetails;
use App\Models\Customer;
use App\Models\Expenditure;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use App\Models\Sales;
use App\Models\SplitPayments;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                $getTransactions = Transaction::whereBetween(DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                    ->with('customer')
                                    ->with(['user' => function ($q) {
                                        $q->withTrashed();
                                    }])
                                    ->with(['sales' => function($q){
                                        $q->join('products', 'sales.product_id', 'products.id')->withTrashed();
                                    }])
                                    ->orderBy('id', 'desc')
                                    ->withTrashed()->get();

                
                $get_sales = Sales::join('transactions', 'sales.ref', 'transactions.id')
                                ->whereBetween(DB::raw('DATE(sales.created_at)'),  [$start_date, $end_date])
                                ->where('transactions.status', '!=', 'cancelled')
                                ->with('product')->with('user')->get();
            
                $get_purchases = PurchaseDetails::whereBetween(DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
                                    ->with('purchase')->with('product')->get();

                $get_expenditures = Expenditure::whereBetween(DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
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
            $getTransactions = Transaction::whereBetween(DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                ->where('type', 'cancelled')
                                ->with('customer')
                                ->with(['sales' => function($q){
                                    $q->join('products', 'sales.product_id', 'products.id');
                                }])->get();

            $get_sales = Sales::whereBetween(DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
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
            $transactions_today = Transaction::where(DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->sum("amount");

            $transactions_next_day = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->sum("amount");

            // get sales between 6am the previous day and 4am today
            $sales_today = Sales::select(DB::raw('sum(price * qty) as  amount'))->join('transactions', 'sales.ref', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(sales.created_at)'),  $start_date)
            ->where(DB::raw('DATE_FORMAT(sales.created_at,"%H:%i")'), ">=", "06:00")->get();
            $sales_today= $sales_today[0]['amount'];
            $sales_next_day = Sales::select(DB::raw('sum(price * qty) as  amount'))->join('transactions', 'sales.ref', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE("sales.created_at")'),  $end_date)
            ->where(DB::raw('DATE_FORMAT(sales.created_at,"%H:%i")'), "<=", "05:00")->get();
            $sales_next_day= $sales_next_day[0]['amount'];

            //get transactions paid with cash
            $cash_today =  Transaction::where(DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "cash")->sum("amount");

            $cash_next_day = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "cash")->sum("amount");

            //get transactions paid with card
            $card_today =  Transaction::where(DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "card")->sum("amount");

            $card_next_day = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "card")->sum("amount");

            //get transactions paid with transfer
            $transfer_today =  Transaction::where(DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "transfer")->sum("amount");

            $transfer_next_day = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "transfer")->sum("amount");

             //get transactions paid with split
             $split_today =  Transaction::where(DB::raw('DATE(`created_at`)'),  $start_date)
             ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "split")->sum("amount");

             $split_next_day = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)
             ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "split")->sum("amount");

             $split_payment_card_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $start_date)
             ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "card")->sum("split_payments.amount");
             $split_payment_card_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $end_date)
             ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "card")->sum("split_payments.amount");
             $split_payment_cash_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $start_date)
             ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "cash")->sum("split_payments.amount");
             $split_payment_cash_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $end_date)
             ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "cash")->sum("split_payments.amount");
             $split_payment_transfer_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $start_date)
             ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "transfer")->sum("split_payments.amount");
             $split_payment_transfer_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $end_date)
             ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "transfer")->sum("split_payments.amount");

             //bank payment received
             $banks = Banks::get();
             $bank_sales = array();
             foreach ($banks as $bank) {
                $card__banktoday =  Transaction::where(DB::raw('DATE(`created_at`)'),  $start_date)
                ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");

                $card_bank_next_day = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)
                ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "card")->where('bank_id', $bank['id'])->sum("amount");

                $split_payment_bank_card_today = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $start_date)
                ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), ">=", "06:00")->where("split_payments.payment_method", "card")->where('split_payments.bank_id', $bank['id'])->sum("split_payments.amount");
                $split_payment_bank_card_next = SplitPayments::select('split_payments.*')->join('transactions', 'split_payments.transaction_id', 'transactions.id')->where('transactions.user_id', $user)->where(DB::raw('DATE(split_payments.created_at)'),  $end_date)
                ->where(DB::raw('DATE_FORMAT(split_payments.created_at,"%H:%i")'), "<=", "05:00")->where("split_payments.payment_method", "card")->where('split_payments.bank_id', $bank['id'])->sum("split_payments.amount");

                array_push($bank_sales, [
                    "bank_name" => $bank['name'],
                    'amount' => $card__banktoday +  $card_bank_next_day +  $split_payment_bank_card_today + $split_payment_bank_card_next
                ]);
             }

             // get comlementary
            $complementary_today = Transaction::where(DB::raw('DATE(`created_at`)'),  $start_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), ">=", "06:00")->where('user_id', $user)->where("payment_method", "complementary")->sum("amount");


            $complementary_next = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('user_id', $user)->where("payment_method", "complementary")->sum("amount");

            $oustanding = Transaction::where(DB::raw('DATE(`created_at`)'),  $end_date)->where('user_id', $user)->with("sales")
            ->where(DB::raw('DATE_FORMAT(`created_at`,"%H:%i")'), "<=", "05:00")->where('status', 'pending')->with("split")->get();


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
            $transactions = Transaction::whereBetween('created_at',  [$start_date, $end_date])
            ->with('split')->with("user")->with('sales')->sum("amount");
            
            $sales = Sales::select(DB::raw('sum(price * qty) as  amount'))->whereBetween('created_at',  [$start_date, $end_date])->get();
            $sales = $sales[0]['amount'];

            //get transactions paid with cash
            $cash =  Transaction::whereBetween('created_at',  [$start_date, $end_date])->where("payment_method", "cash")->sum("amount");

            //get transactions paid with card
            $card =  Transaction::whereBetween('created_at',  [$start_date, $end_date])
                    ->where("payment_method", "card")
                    ->sum("amount");

            //get transactions paid with transfer
            $transfer =  Transaction::whereBetween('created_at',  [$start_date, $end_date])
                        ->where("payment_method", "transfer")
                        ->sum("amount");

             //get transactions paid with split
             $split =  Transaction::whereBetween('created_at',  [$start_date, $end_date])
                        ->where("payment_method", "split")
                        ->sum("amount");

             $split_payment_card = SplitPayments::whereBetween('created_at',  [$start_date, $end_date])
                                    ->where("payment_method", "card")
                                    ->sum("amount");

             $split_payment_cash = SplitPayments::whereBetween('created_at',  [$start_date, $end_date])
                                    ->where("payment_method", "cash")
                                    ->sum("amount");

             $split_payment_transfer = SplitPayments::whereBetween('created_at',  [$start_date, $end_date])
                                        ->where("payment_method", "transfer")
                                        ->sum("amount");
            
             //bank payment received
             $banks = Banks::get();
             $bank_sales = array();
             foreach ($banks as $bank) {
                $card__banktoday =  Transaction::whereBetween('created_at',  [$start_date, $end_date])
                                    ->where("payment_method", "card")
                                    ->where('bank_id', $bank['id'])
                                    ->sum("amount");

                $split_payment_bank_card_today = SplitPayments::whereBetween('created_at',  [$start_date, $end_date])
                                                    ->where("payment_method", "card")
                                                    ->where('bank_id', $bank['id'])
                                                    ->sum("amount");
                
                array_push($bank_sales, [
                    "bank_name" => $bank['name'],
                    'amount' => $card__banktoday +  $split_payment_bank_card_today
                ]);
             }
             // get comlementary
            $complementary = Transaction::whereBetween('created_at',  [$start_date, $end_date])
                                ->where("payment_method", "complementary")
                                ->sum("amount");

            
            $oustanding = Transaction::with(["sales" => function($q){
                $q->with('product');
            }])->with("split")->whereBetween('created_at',  [$start_date, $end_date])
            ->with(['user' => function ($q) {
                $q->withTrashed();
            }])->where("status", "pending")->get();

            $sold_items = Transaction::with(["sales" => function($q){
                $q->with('product');
            }])->with("split")->whereBetween('created_at',  [$start_date, $end_date])
            ->with(['user' => function ($q) {
                $q->withTrashed();
            }])->where("status", "completed")->get();


            $void_items = Transaction::with(["sales" => function($q){
                $q->with('product')->withTrashed();
            }])->whereBetween('created_at',  [$start_date, $end_date])
            ->with(['user' => function ($q) {
                $q->withTrashed();
            }])->where("status", "cancelled")->withTrashed()->get();

            $summary = [
                "expected_amount" => $sales == null ? 0 : $sales,//$this->getVat(),
                "paid_amount" => $transactions,
                "cash" => $cash + $split_payment_cash,
                "card" => $card + $split_payment_card,
                "transfer" =>$transfer + $split_payment_transfer,
                "split_payments" =>$split,
                "split_payments_card" => $split_payment_card,
                "split_payments_transfer" => $split_payment_transfer,
                "split_payments_cash" => $split_payment_cash,
                "banks" =>$bank_sales,
                "complementary" => $complementary,
                "oustanding" => $oustanding,
                "sold_items" => $sold_items,
                "void_items" => $void_items,
                
            ];

            return res_success('report', $summary);

        }
    }
    public function getVat($val){
        $vat = BusinessDetails::first()->vat;
        $total = (doubleval($vat)/100) * ($val) + $val;

        return $total;
    }

    public function getSalesPerformance(Request $request){
        $sales = Transaction::select(
                    DB::raw('DATE(created_at) as sale_date'), // Extract the date portion
                    DB::raw('SUM(amount) as total_amount')   // Sum the amount per day
                )
                ->where('type', 'sold')
                ->whereBetween(DB::raw('DATE(created_at)'), [$request['start_date'], $request['end_date']])
                ->where('status', 'completed')
                ->groupBy('sale_date') // Group by the extracted date
                ->orderBy('sale_date') // Optionally order by the date
                ->get();

        return res_success('success', $sales);
    }

    public function getOpexPerformance(Request $request){
        $start_date  = $request['start_date'];
        $end_date = $request['end_date'];
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if($validated){
            $report = Expenditure::select(DB::raw('date(created_at) as request_date'), 
                DB::raw('SUM(amount) as total_amount')   // Sum the amount per day
            )->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])
            ->whereHas('type', function ($query) {
                $query->where('expenditure_type', 'opex'); // Assuming 'name' is a column in the expenditure_type table
        })
                                ->with('type')->with('user')
                                ->groupBy('request_date') // Group by the extracted date
                                ->orderBy('request_date') // 
                                ->get();
            return res_success('expenditures', $report);
        }
    }

    public function getCustomerInsightPerformance(Request $request){
            $report = Customer::select(DB::raw('fullname') , DB::raw('ABS(wallet_balance) as wallet_balance'))
                        ->where('wallet_balance', "<", 0);
            
            return res_success('expenditures', [$report->get(), $report->sum('wallet_balance')]);
    }

    public function getCogs(Request $request){
        $start_date  = $request['start_date'];
        $end_date = $request['end_date'];
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if($validated){
            $report = Purchase::select(DB::raw('date(created_at) as purchase_date'), 
                                DB::raw('SUM(price) as total_amount'), DB::raw('SUM(added_costs) as other_cogs')   // Sum the amount per day
                                )->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])
                                ->groupBy('purchase_date') // Group by the extracted date
                                ->orderBy('purchase_date') // 
                                ->get();
            return res_success('cogs', $report);

        }
    }

    public function getPaymentMethodPerformance(Request $request){
        $methods = ['cash', 'transfer', 'card', 'wallet', 'on_credit', 'pos', 'split', "complementary"];
        $payment_method_distr = [];
        foreach ($methods as $key => $method) {
            $sales = Transaction::where('type', 'sold')
                        ->whereBetween(DB::raw('DATE(created_at)'), [$request['start_date'], $request['end_date']])
                        ->where('status', 'completed')
                        ->where('payment_method', $method)
                        ->sum('amount');

                        $payment_method_distr[$method][] =  $sales;
        }
        

        return res_success('success', $payment_method_distr);

    }

    public function getProfitLoss(Request $request){
        // const turnover = computed(() => {
        //     const data = monthlyData[selectedMonth.value];
      
        //     return (
        //       data.revenue + data.cloud + data.other_income + data.infracoLegend + data.infracoMinna + data.installationCost + data.new_customers - ((data.breachLegend/100)*data.infracoLegend)
              
        //     );
        //   });
      
        //   const cogs = computed(() => monthlyData[selectedMonth.value].cogs);
        //   const opex = computed(() => monthlyData[selectedMonth.value].opex);
        //   const grossProfit = computed(() => turnover.value - cogs.value);
        //   const totalExpenditure = computed(() => opex.value + depreciation.value + interest.value);
        //   const netProfit = computed(() => grossProfit.value - totalExpenditure.value);
      
        //   const grossProfitMargin = computed(() => (grossProfit.value / turnover.value) * 100);
        //   const netMargin = computed(() => (netProfit.value / turnover.value) * 100);
        $start_date  = $request['start_date'];
        $end_date = $request['end_date'];
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);
        $sales = Transaction::where('type', 'sold')
            ->whereBetween(DB::raw('DATE(created_at)'), [$request['start_date'], $request['end_date']])
            ->where('status', 'completed')
            ->sum('amount');

        $turnover = $sales;
        
        $cogs = Purchase::whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])
                ->sum(DB::raw('price + added_costs'));

        $opex = Expenditure::whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])->whereHas('type', function ($query) {
                    $query->where('expenditure_type', 'opex'); // Assuming 'name' is a column in the expenditure_type table
                })->sum('amount');

        $cogs_exp = Expenditure::whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])->whereHas('type', function ($query) {
                        $query->where('expenditure_type', 'cogs'); // Assuming 'name' is a column in the expenditure_type table
                })->sum('amount');
        
        $gross_profit = $turnover - $cogs;
        $total_expenditure = $opex;
        $net_profit = $gross_profit - $total_expenditure;
        $gross_profit_margin = $turnover > 0 ? ($gross_profit/$turnover) * 100 :0;
        $net_profit_margin = $turnover > 0 ? ($net_profit/$turnover) * 100 : 0;

        $result = [
            "turnover" => $turnover,
            "cogs" => $cogs + $cogs_exp,
            "opex" => $opex,
            "gross_profit" => $gross_profit,
            "total_expenditure" => $total_expenditure,
            "net_profit" => $net_profit,
            "gross_profit_margin" => $gross_profit_margin,
            "net_profit_margin" => $net_profit_margin,
        ];

        return res_success('success', $result);

    }


}
