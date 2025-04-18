<?php

namespace App\Http\Controllers;

use App\Interfaces\ReportRepositoryInterface;
use App\Models\Banks;
use App\Models\BusinessDetails;
use App\Models\Customer;
use App\Models\Expenditure;
use App\Models\Liquidity;
use App\Models\LogisticsAccount;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use App\Models\Sale;
use App\Models\Sales;
use App\Models\SplitPayments;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public $opening_time;
    public $closing_time;
    public $shopId;
    public $reportRepo;
    public function __construct(ReportRepositoryInterface $reportRepo){
        $businessTime = getBusinessTime();
        $this->opening_time = $businessTime['start_time'] ?? "00:00";
        $this->closing_time = $businessTime['closing_time'] ?? "23:59";
        $this->reportRepo = $reportRepo;
    }
    public function general_report(Request $request){
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $shopId = $request->query('shop_id');

        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'platform' => 'required'
        ]);

        if($validated){
            $getTransactions = applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                                    ->with('customer')
                                    ->with(['user' => function ($q) {
                                        $q->withTrashed();
                                    }])
                                    ->with(['sales' => function($q){
                                        $q->join('products', 'sales.product_id', 'products.id')->withTrashed();
                                    }])
                                ->with('deliveryNote')->with('logistics')
                                    ->orderBy('id', 'desc')
                                    ->withTrashed(), $shopId)->get();
                
                $get_sales = Sale::join('transactions', 'sales.ref', 'transactions.id')
                                    ->join('customers', 'transactions.customer_id', 'customers.id')
                                ->whereBetween('transactions.created_at', [$start_date, $end_date])
                                ->where('transactions.status', '!=', 'cancelled')
                                ->where('sales.shop_id', $shopId)
                                ->with('product')->with('user')
                                ->orderBy('sales.created_at', 'desc')->get();
            
                $get_purchases = applyShopFilter( PurchaseDetails::whereBetween('created_at', [$start_date, $end_date])
                                    ->with('purchase')->with('product'), $shopId)->get();

                $get_expenditures = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])
                                    ->with('type')->with('user'), $shopId)->get();

            $report = [
                'transaction' => $getTransactions,
                'sales' => $get_sales,
                'purchases' => $get_purchases,
                'expenditures' => $get_expenditures,
                'query'=> [$start_date, $end_date]
            ];
            return res_success('report', $report);
        }
    }
    public function cancelled_receipt(Request $request){
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);
        $shopId = $request->query('shop_id');

        if($validated){
            $getTransactions = applyShopFilter(Transaction::whereBetween('created_at', [$start_date , $end_date])
                                ->where('type', 'cancelled')
                                ->with('customer')
                                ->with(['sales' => function($q){
                                    $q->join('products', 'sales.product_id', 'products.id');
                                }]), $shopId)->get();

            $get_sales = applyShopFilter(Sale::whereBetween('created_at', [$start_date, $end_date])
                                ->where('type', 'cancelled')
                                ->with('product')
                                ->with('user'), $shopId)->get();
            $report = [
                'transaction' => $getTransactions,
                'sales' => $get_sales,
            ];

            return res_success('report', $report);

        }
    }
    public function generate_sales_report(Request $request){
        $user = $request['user_id'];
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $shopId = $request->query('shop_id');

        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'user_id' => 'required'
        ]);

        if ($validated) {
            // Fetch transactions within the given date range
            $transactions = applyShopFilter(Transaction::whereBetween(DB::raw('created_at'), [$start_date, $end_date])
                                            ->where('user_id', $user),$shopId)->get();
        
            // Calculate total amounts for all payment methods
            $total_paid = $transactions->sum("amount");
        
            $cash_total = $transactions->where("payment_method", "cash")->sum("amount");
            $card_total = $transactions->where("payment_method", "card")->sum("amount");
            $transfer_total = $transactions->where("payment_method", "transfer")->sum("amount");
            $split_total = $transactions->where("payment_method", "split")->sum("amount");
            $complementary_total = $transactions->where("payment_method", "complementary")->sum("amount");
        
            // Fetch split payment details
            $split_payments = applyShopFilter(SplitPayments::select('split_payments.*')
                                ->join('transactions', 'split_payments.transaction_id', 'transactions.id')
                                ->whereBetween(DB::raw('split_payments.created_at'), [$start_date, $end_date])
                                ->where('transactions.user_id', $user),$shopId)->get();
        
            $split_payment_card = $split_payments->where("payment_method", "card")->sum("amount");
            $split_payment_cash = $split_payments->where("payment_method", "cash")->sum("amount");
            $split_payment_transfer = $split_payments->where("payment_method", "transfer")->sum("amount");
        
            // Fetch sales within the given date range
            $sales = applyShopFilter(Sale::select(DB::raw('sum(price * qty) as amount'))
                        ->join('transactions', 'sales.ref', 'transactions.id')
                        ->where('transactions.user_id', $user)
                        ->whereBetween(DB::raw('sales.created_at'), [$start_date, $end_date]), $shopId)->first();
        
            $total_sales = $sales->amount ?? 0;
        
            // Fetch outstanding transactions
            $outstanding = applyShopFilter(Transaction::whereBetween(DB::raw('created_at'), [$start_date, $end_date])
                                ->where('user_id', $user)
                                ->where('status', 'pending')
                                ->with('sales', 'split'), $shopId
                                )->get();
        
            // Summarize the data
            $summary = [
                "expected_amount" => $this->getVat($total_sales),
                "paid_amount" => $total_paid,
                "cash" => $cash_total + $split_payment_cash,
                "card" => $card_total + $split_payment_card,
                "transfer" => $transfer_total + $split_payment_transfer,
                "split_payments" => $split_total,
                "split_payments_card" => $split_payment_card,
                "split_payments_transfer" => $split_payment_transfer,
                "split_payments_cash" => $split_payment_cash,
                "complementary" => $complementary_total,
                "outstanding" => $outstanding
            ];
        
            return res_success('report', $summary);
        }
        
    }
    public function generate_user_report(Request $request, $id){
            $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
            $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;    
            $shopId = $request->query('shop_id');

            $validated = Validator::make($request->all(), [
                'start_date' => 'required',
                'end_date' => 'required',
            ]);
    
            if ($validated) {
                // get transactions between 6am the previous day - 4am today
                $transactions = applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                                ->with('split')->with("user")->with('sales')->where('user_id', $id), $shopId)
                                ->sum("amount");
                
                $sales = applyShopFilter(Sale::select(DB::raw('sum(price * qty) as  amount'))
                            ->whereBetween('created_at', [$start_date, $end_date])
                            ->where('user_id', $id), $shopId)->get();
                $sales = $sales[0]['amount'];
    
                //get transactions paid with cash
                $cash =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                            ->where("payment_method", "cash")->where('user_id', $id), $shopId)->sum("amount");
    
                //get transactions paid with card
                $card =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                        ->where("payment_method", "card")->where('user_id', $id), $shopId)->sum("amount");
    
                //get transactions paid with transfer
                $transfer =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                            ->where("payment_method", "transfer")->where('user_id', $id), $shopId
                            )->sum("amount");
    
                 //get transactions paid with split
                 $split =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                            ->where("payment_method", "split")->where('user_id', $id), $shopId
                            )->sum("amount");
    
                $split_payment_card = applyShopFilter(SplitPayments::whereHas('transaction', function ($query) use ($id) {
                                $query->where('user_id', $id);
                            })
                            ->whereBetween('created_at', [$start_date, $end_date])
                            ->where("payment_method", "card"), $shopId
                            )->sum("amount");
                            
    
                 $split_payment_cash = applyShopFilter(SplitPayments::whereHas('transaction', function ($query) use ($id) {
                                            $query->where('user_id', $id);
                                        })->whereBetween('created_at', [$start_date, $end_date])
                                        ->where("payment_method", "cash"), $shopId)
                                        ->sum("amount");
    
                 $split_payment_transfer = applyShopFilter(SplitPayments::whereHas('transaction', function ($query) use ($id) {
                                                $query->where('user_id', $id);
                                            })->whereBetween('created_at', [$start_date, $end_date])
                                            ->where("payment_method", "transfer"), $shopId)
                                            ->sum("amount");
                
                 //bank payment received
                 $banks = Banks::get();
                 $bank_sales = array();
                 foreach ($banks as $bank) {
                    $card__banktoday =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                                        ->where("payment_method", "card")
                                        ->where('bank_id', $bank['id'])->where('user_id', $id), $shopId)
                                        ->sum("amount");
    
                    $split_payment_bank_card_today = applyShopFilter(SplitPayments::whereHas('transaction', function ($query) use ($id) {
                                                            $query->where('user_id', $id);
                                                        })->whereBetween('created_at', [$start_date, $end_date])
                                                        ->where("payment_method", "card")
                                                        ->where('bank_id', $bank['id']), $shopId)
                                                        ->sum("amount");
                    
                    array_push($bank_sales, [
                        "bank_name" => $bank['name'],
                        'amount' => $card__banktoday +  $split_payment_bank_card_today
                    ]);
                 }
                 // get comlementary
                $complementary = applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                                    ->where("payment_method", "complementary")->where('user_id', $id), $shopId)
                                    ->sum("amount");
    
                
                $oustanding = applyShopFilter(Transaction::with(["sales" => function($q){
                            $q->with('product');
                        }])->with("split")->whereBetween('created_at', [$start_date, $end_date])
                        ->with(['user' => function ($q) {
                            $q->withTrashed();
                        }])->where("status", "pending")->where('user_id', $id), $shopId)->get();
    
                $sold_items = applyShopFilter(Transaction::with(["sales" => function($q){
                    $q->with('product');
                }])->with("split")->whereBetween('created_at', [$start_date, $end_date])
                ->with(['user' => function ($q) {
                    $q->withTrashed();
                }])->where("status", "completed")->where('user_id', $id), $shopId)->get();
    
    
                $void_items = applyShopFilter(Transaction::with(["sales" => function($q){
                    $q->with('product')->withTrashed();
                }])->whereBetween('created_at', [$start_date, $end_date])
                ->with(['user' => function ($q) {
                    $q->withTrashed();
                }])->where("status", "cancelled")->where('user_id', $id)->withTrashed(), $shopId)->get();
    
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
    public function generate_report(Request $request){
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;

        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

        $shopId = request()->query('shop_id');

        if ($validated) {
            // get transactions between 6am the previous day - 4am today
            $transactions = applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
            ->with('split')->with("user")->with('sales'), $shopId)->sum("amount");
            
            $sales = applyShopFilter(Sale::select(DB::raw('sum(price * qty) as  amount'))->whereBetween('created_at', [$start_date, $end_date]), $shopId)->get();
            $sales = $sales[0]['amount'];

            //get transactions paid with cash
            $cash =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])->where("payment_method", "cash"), $shopId)->sum("amount");

            //get transactions paid with card
            $card =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                    ->where("payment_method", "card"), $shopId)
                    ->sum("amount");

            //get transactions paid with transfer
            $transfer =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                        ->where("payment_method", "transfer"), $shopId)
                        ->sum("amount");

             //get transactions paid with split
             $split =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                        ->where("payment_method", "split"), $shopId)
                        ->sum("amount");

             $split_payment_card = applyShopFilter(SplitPayments::whereBetween('created_at', [$start_date, $end_date])
                                    ->where("payment_method", "card"), $shopId)
                                    ->sum("amount");

             $split_payment_cash = applyShopFilter(SplitPayments::whereBetween('created_at', [$start_date, $end_date])
                                    ->where("payment_method", "cash"), $shopId)
                                    ->sum("amount");

             $split_payment_transfer = applyShopFilter(SplitPayments::whereBetween('created_at', [$start_date, $end_date])
                                        ->where("payment_method", "transfer"), $shopId)
                                        ->sum("amount");
            
             //bank payment received
             $banks = Banks::get();
             $bank_sales = array();
             foreach ($banks as $bank) {
                $card__banktoday =  applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                                    ->where("payment_method", "card")
                                    ->where('bank_id', $bank['id']), $shopId)
                                    ->sum("amount");

                $split_payment_bank_card_today = applyShopFilter(SplitPayments::whereBetween('created_at', [$start_date, $end_date])
                                                    ->where("payment_method", "card")
                                                    ->where('bank_id', $bank['id']), $shopId)
                                                    ->sum("amount");
                
                array_push($bank_sales, [
                    "bank_name" => $bank['name'],
                    'amount' => $card__banktoday +  $split_payment_bank_card_today
                ]);
             }
             // get comlementary
            $complementary = applyShopFilter(Transaction::whereBetween('created_at', [$start_date, $end_date])
                                ->where("payment_method", "complementary"), $shopId)
                                ->sum("amount");

            
            $oustanding = applyShopFilter(Transaction::with(["sales" => function($q){
                $q->with('product');
            }])->with("split")->whereBetween('created_at', [$start_date, $end_date])
            ->with(['user' => function ($q) {
                $q->withTrashed();
            }])->where("status", "pending"), $shopId)->get();

            $sold_items = applyShopFilter(Transaction::with(["sales" => function($q){
                $q->with('product');
            }])->with("split")->whereBetween('created_at', [$start_date, $end_date])
            ->with(['user' => function ($q) {
                $q->withTrashed();
            }])->where("status", "completed"), $shopId)->get();


            $void_items = applyShopFilter(Transaction::with(["sales" => function($q){
                $q->with('product')->withTrashed();
            }])->whereBetween('created_at', [$start_date, $end_date])
            ->with(['user' => function ($q) {
                $q->withTrashed();
            }])->where("status", "cancelled")->withTrashed(), $shopId)->get();

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
                "void_items" => $void_items
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
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $shopId = request()->query('shop_id');
        $sales = applyShopFilter(Transaction::select(
                    DB::raw('DATE(created_at) as sale_date'), // Extract the date portion
                    DB::raw('SUM(amount) as total_amount')   // Sum the amount per day
                )
                ->where('type', 'sold')
                ->whereBetween('created_at', [$start_date, $end_date])
                ->where('status', 'completed')
                ->groupBy('sale_date') // Group by the extracted date
                ->orderBy('sale_date') // Optionally order by the date
                , $shopId)->get();

        return res_success('success', $sales);
    }
    public function getOpexPerformance(Request $request){
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);
        $shopId = request()->query('shop_id');    

        if($validated){
            $report =       applyShopFilter(Expenditure::select(DB::raw('date(created_at) as request_date'), 
                                        DB::raw('SUM(amount) as total_amount')   // Sum the amount per day
                                    )->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])
                                    ->whereHas('type', function ($query) {
                                        $query->where('expenditure_type', 'opex'); // Assuming 'name' is a column in the expenditure_type table
                                })
                                ->with('type')->with('user')
                                ->groupBy('request_date') // Group by the extracted date
                                ->orderBy('request_date'), $shopId) // 
                                ->get();
            return res_success('expenditures', $report);
        }
    }
    public function getCustomerInsightPerformance(Request $request){
        $shopId = request()->query('shop_id');
            $report = applyShopFilter(Customer::select(DB::raw('fullname') , DB::raw('ABS(wallet_balance) as wallet_balance'))
                        ->where('wallet_balance', "<", 0), $shopId);
            
            return res_success('expenditures', [$report->get(), $report->sum('wallet_balance')]);
    }
    public function getPayables(Request $request){
        $shopId = request()->query('shop_id');
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        // payables are registered in liquidity as debit, but on credit
        $expenditures_credit = applyShopFilter(Expenditure::where('payment_status', 'not_paid')
                                ->where('payment_method', 'on_credit')
                                ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                ->get();
        
        $expenditures_part_payment = applyShopFilter(Expenditure::where('payment_status', 'not_paid')
                                        ->where('payment_method', 'part_payment')
                                        ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                        ->get();
        
        $purchases_part_credit = applyShopFilter(PurchaseDetails::where('payment_status', 'not_paid')
                                    ->where('payment_method', 'on_credit')
                                    ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                    ->get();
        
        $purchases_part_payment = applyShopFilter(PurchaseDetails::where('payment_status', 'not_paid')
                                    ->where('payment_method', 'part_payment')
                                    ->whereBetween('created_at', [$start_date, $end_date]), $shopId)->get();

            $payable = [
                "expenditures_credit" => $expenditures_credit,
                "expenditures_part_payment" => $expenditures_part_payment,
                "purchases_part_credit" => $purchases_part_credit,
                "purchases_part_payment" => $purchases_part_payment
            ];

            return res_success('succes', $payable);
    }

    public function downloadReport(Request $request){

        return $this->reportRepo->downloadReport($request->all());
    }
    public function getCogs(Request $request){
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        $shopId = request()->query('shop_id');  

        if($validated){
        // Fetching reports
        $report = applyShopFilter( Purchase::select(
                DB::raw('DATE(created_at) as purchase_date'),
                DB::raw('SUM(price) as total_amount'),
                DB::raw('SUM(added_costs) as other_cogs')
            )
            ->whereBetween('created_at', [$start_date, $end_date])
            ->groupBy('purchase_date')
            ->orderBy('purchase_date'), $shopId)
            ->get();

        // Fetching expenditures
        $cogs_exp = applyShopFilter(Expenditure::select(
                DB::raw('DATE(created_at) as purchase_date'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->whereBetween('created_at', [$start_date, $end_date])
            ->whereHas('type', function ($query) {
                $query->where('expenditure_type', 'cogs');
            })
            ->groupBy('purchase_date')
            ->orderBy('purchase_date'), $shopId)
            ->get();

        // Merge both collections
        $merged = $report->concat($cogs_exp);

        // Group by purchase_date and sum the amounts
        $finalReport = $merged->groupBy('purchase_date')->map(function ($group) {
            return [
                'purchase_date' => $group->first()->purchase_date,
                'total_amount' => $group->sum('total_amount'),
                'other_cogs' => $group->sum('other_cogs'), // This field will default to 0 for expenditures
            ];
        });

        // Convert back to a collection or output it
        $finalReport = $finalReport->values();

                  
            return res_success('cogs', $finalReport);

        }
    }
    public function getPaymentMethodPerformance(Request $request){
        $shopId = request()->query('shop_id');
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $methods = ['cash', 'transfer', 'card', 'wallet', 'on_credit', 'pos', 'split', "complementary"];
        $payment_method_distr = [];
        foreach ($methods as $key => $method) {
            $sales = applyShopFilter(Transaction::where('type', 'sold')
                        ->whereBetween('created_at', [$start_date, $end_date])
                        ->where('status', 'completed')
                        ->where('payment_method', $method), $shopId)
                        ->sum('amount');

                        $payment_method_distr[$method][] =  $sales;
        }
        

        return res_success('success', $payment_method_distr);

    }
    public function getProfitLoss(Request $request){
        $shopId = request()->query('shop_id');
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
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;

        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        $sales = applyShopFilter(Transaction::where('type', 'sold')
                    ->whereBetween('created_at', [$start_date, $end_date])
                    ->where('status', 'completed'), $shopId
                    )->sum('amount');

        $turnover = $sales;
        
        $cogs = applyShopFilter(Purchase::whereBetween('created_at', [$start_date, $end_date]), $shopId)->sum(DB::raw('price + added_costs'));

        $opex = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])->whereHas('type', function ($query) {
                    $query->where('expenditure_type', 'opex'); // Assuming 'name' is a column in the expenditure_type table
                }), $shopId)->sum('amount');
        
        $assets = applyShopFilter(Expenditure::with('type')->whereHas('type', function ($query) {
            $query->where('expenditure_type', 'capex');}), $shopId)->get();

        $annual_depreciation = 0;
        $monthly_depreciation = 0;
        // Loop through each asset
        foreach ($assets as $asset) {
            $amount = $asset->amount;
            $usefulLife = $asset->type->useful_life * 12;
            $sal_val = $asset->type->salvage_value;
    
            // Calculate salvage value (13.3% of amount)
            $salvageValue = $amount * ($sal_val/100);
    
            // Calculate depreciable amount
            $depreciableAmount = $amount - $salvageValue;
    
            // Calculate annual depreciation
            $annualDepreciation = $depreciableAmount / $usefulLife;
            $annual_depreciation += $annualDepreciation;
    
            // Calculate monthly depreciation
            $monthlyDepreciation = $annualDepreciation / 12;
            $monthly_depreciation += $monthlyDepreciation;

        }

        $cogs_exp = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])
                                    ->whereHas('type', function ($query) {
                                            $query->where('expenditure_type', 'cogs'); // Assuming 'name' is a column in the expenditure_type table
                                    }), $shopId)->sum('amount');
        
        $gross_profit = $turnover - ($cogs + $cogs_exp);

        $total_expenditure = $opex + $cogs + $cogs_exp + $monthly_depreciation;
        $net_profit = $turnover - $total_expenditure;
        $gross_profit_margin = $turnover > 0 ? ($gross_profit/$turnover) * 100 :0;
        $net_profit_margin = $turnover > 0 ? ($net_profit/$turnover) * 100 : 0;

        $result = [
            "turnover" => $turnover,
            "cogs" => $cogs + $cogs_exp,
            "opex" => $opex,
            "gross_profit" => $gross_profit,
            "depreciation" => $monthly_depreciation,
            "total_expenditure" => $total_expenditure,
            "net_profit" => $net_profit,
            "gross_profit_margin" => $gross_profit_margin,
            "net_profit_margin" => $net_profit_margin,
            "lets_See" =>[$opex , $cogs ,$cogs_exp ,$monthly_depreciation. $assets],
            "accounting_balance" => $this->getAccountingBalance($start_date, $end_date)

        ];

        return res_success('success', $result);

    }
    public function getAccountingBalance($start_date, $end_date)
    {
        $shopId = request()->query('shop_id');
        // Opening Cash Balance (sold transactions before start_date)
        $opening_cash = applyShopFilter(Transaction::where('type', 'sold')
            ->where('created_at', '<', $start_date)
            ->where('status', 'completed'), $shopId)
            ->sum('amount');
    
        // Opening Receivables Balance (on_credit transactions before start_date)
        $opening_receivables = applyShopFilter(Transaction::where('type', 'on_credit')
            ->where('created_at', '<', $start_date)
            ->where('status', 'completed'), $shopId)
            ->sum('amount');
    
        // Total Cash (sold transactions within period)
        $cash_within_period = applyShopFilter(Transaction::where('type', 'sold')
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where('status', 'completed'), $shopId)
            ->sum('amount');
    
        // Total Receivables (on_credit transactions within period)
        $receivables_within_period = applyShopFilter(Transaction::where('type', 'on_credit')
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where('status', 'completed'), $shopId)
            ->sum('amount');
    
        // Calculate Closing Balances
        $closing_cash = $opening_cash + $cash_within_period;
        $closing_receivables = $opening_receivables + $receivables_within_period;
    
        return  [
            "opening_cash_balance" => $opening_cash,
            "opening_receivables_balance" => $opening_receivables,
            "closing_cash_balance" => $closing_cash,
            "closing_receivables_balance" => $closing_receivables
        ];
    }

    public function getBankAccountBalance(){
        $shopId = request()->query('shop_id');
        $liq = applyShopFilter(Liquidity::latest(), $shopId)->first();

        if($liq){
            return res_success('amount', $liq->current_balance);
        }
        return res_success('amount', 0);
    }

    public function getBankStatement(Request $request){
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $shopId = request()->query('shop_id');

        $liquidity = applyShopFilter(Liquidity::whereBetween("created_at", [$start_date, $end_date]), $shopId)
                        ->get();

        return res_success('message', $liquidity);
    }

    public function getLogisticsStatement(Request $request){
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $this->opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $this->closing_time;
        $shopId = request()->query('shop_id');

        $logistics = applyShopFilter(LogisticsAccount::whereBetween("created_at", [$start_date, $end_date]), $shopId)
                        ->get();

        return res_success('message', $logistics);
    }
}
