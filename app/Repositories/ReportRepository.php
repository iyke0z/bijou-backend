<?php

namespace App\Repositories;

use App\Interfaces\ReportRepositoryInterface;
use App\Models\Budget;
use App\Models\BusinessDetails;
use App\Models\Customer;
use App\Models\Expenditure;
use App\Models\Liquidity;
use App\Models\LogisticsAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\SplitPayments;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportRepository implements ReportRepositoryInterface{
    public function downloadReport($request){
        $businessTime = getBusinessTime();
        $opening_time = $businessTime['start_time'] ?? "00=>00";
        $closing_time = $businessTime['closing_time'] ?? "23=>59";
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d') . ' ' . $opening_time;
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d') . ' ' . $closing_time;
       
        $shopId = $request['shop_id'];
        $shopName = "All Shops";
        if($shopId != 0){
            $shop = Shop::find($shopId);
            $shopName = $shop->title;
        }

        $sales = applyShopFilter(Transaction::where('type', 'sold')
                    ->whereBetween('created_at', [$start_date, $end_date])
                    ->where('status', 'completed'), $shopId
                    )->sum('amount');
        
        
        $sales_by_product = $this->getSalesByProduct($start_date, $end_date, $shopId);
        
        $logistics = applyShopFilter(LogisticsAccount::where('type', 'credit')->whereBetween('created_at', [$start_date, $end_date]), $shopId)->sum('amount');
        $logistics_expenditure = applyShopFilter(LogisticsAccount::where('type', 'debit')->whereBetween('created_at', [$start_date, $end_date]), $shopId)->sum('amount');

                
        $turnover = $sales + $logistics;

        $marketing_cost =  applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])->whereHas('type', function ($query) {
            $query->where('expenditure_type', 'marketing_cost'); // Assuming 'name' is a column in the expenditure_type table
        }), $shopId)->sum('amount');
        
        $cogs = applyShopFilter(Purchase::whereBetween('created_at', [$start_date, $end_date]), $shopId)->sum(DB::raw('price + added_costs'));

        $opex = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])->whereHas('type', function ($query) {
                    $query->where('expenditure_type', 'opex'); // Assuming 'name' is a column in the expenditure_type table
                }), $shopId)->sum('amount');

        $salaries = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])->whereHas('type', function ($query) {
                    $query->where('expenditure_type', 'salaries'); // Assuming 'name' is a column in the expenditure_type table
                }), $shopId)->sum('amount');
    
        $utilities = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])->whereHas('type', function ($query) {
                    $query->where('expenditure_type', 'utilities'); // Assuming 'name' is a column in the expenditure_type table
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

        $products = applyShopFilter(Product::where('id', '>', 0), $shopId)->get();
        $invetory = 0;
        // get the purchase for each product
        foreach ($products as $product) {
            $purchases = applyShopFilter(PurchaseDetails::where('product_id', $product->id)->whereBetween('created_at', [$start_date, $end_date]), $shopId)->get();
            foreach ($purchases as $purchase) {
                $invetory += $purchase->cost * ($purchase->qty + $purchase->pervious_stock);
            }
        }

        $receivables_within_period = applyShopFilter(Transaction::where('type', 'on_credit')
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where('status', 'completed'), $shopId)
            ->sum('amount');

        $receivables_within_period_details =  applyShopFilter(Customer::select(DB::raw('fullname') , DB::raw('ABS(wallet_balance) as wallet_balance'))
                        ->where('wallet_balance', "<", 0), $shopId)->get();
            
            

            $expenditures_credit = applyShopFilter(Expenditure::where('payment_status', 'not_paid')
            ->where('payment_method', 'on_credit')
            ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
            ->sum('amount');

            $expenditures_part_payment = applyShopFilter(Expenditure::where('payment_status', 'not_paid')
                                ->where('payment_method', 'part_payment')
                                ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                ->sum('amount');

            $purchases_part_credit = applyShopFilter(PurchaseDetails::where('payment_status', 'not_paid')
                            ->where('payment_method', 'on_credit')
                            ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                            ->selectRaw('SUM(cost * qty) as total')
                            ->value('total');

            $purchases_part_payment = applyShopFilter(PurchaseDetails::with('product')->where('payment_status', 'not_paid')
                ->where('payment_method', 'part_payment')
                ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                ->selectRaw('SUM(cost * qty) as total')
                ->value('total');
            
            $part_payment_made = applyShopFilter(PurchaseDetails::where('payment_method', 'part_payment')
                ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                ->sum('part_payment_amount');
            $payables = ($expenditures_credit + $purchases_part_credit + $expenditures_part_payment + $purchases_part_payment) - $part_payment_made;


            $payable_details = [
                'expenditures_credit' => applyShopFilter(Expenditure::where('payment_status', 'not_paid')
                                            ->where('payment_method', 'on_credit')
                                            ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                            ->get(),
                'expenditures_part_payment' => applyShopFilter(PurchaseDetails::with('product')->where('payment_status', 'not_paid')
                                            ->where('payment_method', 'on_credit')
                                            ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                            ->get(),
                'purchases_part_credit' => applyShopFilter(PurchaseDetails::with('product')->where('payment_status', 'not_paid')
                                            ->where('payment_method', 'on_credit')
                                            ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                            ->get(),
                'purchases_part_payment' => applyShopFilter(PurchaseDetails::with('product')->where('payment_status', 'not_paid')
                                            ->where('payment_method', 'part_payment')
                                            ->whereBetween('created_at', [$start_date, $end_date]), $shopId)
                                            ->get(),
                
            ];

            // Fetch transactions within the given date range
            $transactions = applyShopFilter(Transaction::whereBetween(DB::raw('created_at'), [$start_date, $end_date])
                                           ,$shopId)->get();
        
            // Calculate total amounts for all payment methods        
            $cash_total = $transactions->where("payment_method", "cash")->sum("amount");
            $card_total = $transactions->where("payment_method", "card")->sum("amount");
            $transfer_total = $transactions->where("payment_method", "transfer")->sum("amount");
            $split_total = $transactions->where("payment_method", "split")->sum("amount");
        
            // Fetch split payment details
            $split_payments = applyShopFilter(SplitPayments::select('split_payments.*')
                                ->join('transactions', 'split_payments.transaction_id', 'transactions.id')
                                ->whereBetween(DB::raw('split_payments.created_at'), [$start_date, $end_date])
                                ,$shopId)->get();
        
            $split_payment_card = $split_payments->where("payment_method", "card")->sum("amount");
            $split_payment_cash = $split_payments->where("payment_method", "cash")->sum("amount");
            $split_payment_transfer = $split_payments->where("payment_method", "transfer")->sum("amount");
        
            // Fetch sales within the given date range
            $sales = applyShopFilter(Sale::select(DB::raw('sum(price * qty) as amount'))
                        ->join('transactions', 'sales.ref', 'transactions.id')
                        ->whereBetween(DB::raw('sales.created_at'), [$start_date, $end_date]), $shopId)->first();
        
            $total_sales = $sales->amount ?? 0;
        
            // Fetch outstanding transactions
            $outstanding = applyShopFilter(Transaction::whereBetween(DB::raw('created_at'), [$start_date, $end_date])
                                ->where('status', 'pending')
                                ->with('sales', 'split'), $shopId
                                )->get();
        
        $cash = $cash_total + $split_payment_cash;
        $bank = $transfer_total + $split_payment_transfer + $card_total + $split_payment_card;
        $totalBalance = $cash + $bank;

        $cogs_exp = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])
                                    ->whereHas('type', function ($query) {
                                            $query->where('expenditure_type', 'cogs'); // Assuming 'name' is a column in the expenditure_type table
                                    }), $shopId)->sum('amount');
        
        $gross_profit = $turnover - ($cogs + $cogs_exp);

        $total_expenditure = $opex + $marketing_cost + $salaries + $utilities + $cogs + $cogs_exp + $monthly_depreciation;
        $net_profit = $turnover - $total_expenditure;
        $gross_profit_margin = $turnover > 0 ? ($gross_profit/$turnover) * 100 :0;
        $net_profit_margin = $turnover > 0 ? ($net_profit/$turnover) * 100 : 0;
        $customers = Customer::all()->count();
        $operating_profit = $turnover - $cogs - $opex;

        if ($turnover > 0) {
            $operating_profit_margin = ($operating_profit / $turnover) * 100;
        } else {
            $operating_profit_margin = 0; // or handle it with another value/message
        }


        $purchase_details = applyShopFilter(Purchase::with('user')->with('documents')->with(['purchase_detail' => function($q) {
            $q->with('product');
        }]), $shopId)->get();

            foreach ($purchase_details as $purchase) {
                $purchase_detail = PurchaseDetails::where('purchase_id', $purchase->id)->get();
                $totalBalance = 0;
                foreach ($purchase_detail as $detail) {
                    if (in_array($detail->payment_method, ['part_payment', 'on_credit']) || $detail->payment_status == 'not_paid') {
                        $purchase->purchase_detail = $detail;
                        $balance = $detail->cost * $detail->qty - $detail->part_payment_amount;
                        $totalBalance += $balance;
                        $purchase['total_balance'] = $totalBalance;
                    }else{
                        $purchase['total_balance'] += $detail->cost * $detail->qty;
                    }
                }
            }

        $budgeted_revenue = applyShopFilter(Budget::where('budget_type', 'revenue')->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date]), $shopId)
                            ->sum('budget_amount');
        $budgeted_expenditure = applyShopFilter(Budget::where('budget_type', 'expenditure')->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date]), $shopId)
        ->sum('budget_amount');
        
        // Quadrant	Primary Heads
        // Assets	Cash, Bank, Receivables, Inventory, Equipment
        // Liabilities	Payables, Loans, Tax Liabilities
        // Equity	Capital, Retained Earnings, Drawings
        // Income/Expenses	Sales, COGS, Salaries, Utilities

        $bs_assets = [
            'cash' => $cash,
            'bank'=> $bank,
            'receivables' => $receivables_within_period,
            'inventory' => $invetory,
            'equipment' => $assets,
        ];

        $liabilities = [
            'payables' => $payables,
            'loans' => 0, // Assuming you have a way to calculate loans
            'tax_liabilities' => 0, // Assuming you have a way to calculate tax liabilities
        ];

        $equity = [
            'capital' => ($cash + $receivables_within_period + $invetory) - $payables,
            'retained_earnings' => $net_profit,
            'drawings' => 0, // Assuming you have a way to calculate drawings
        ];

        $income_expenses = [
            'sales' => $turnover,
            'cogs' => $cogs,
            'salaries_utilities' => $salaries + $utilities, // Assuming you have a way to calculate salaries
        ];

        $report = [
            "report"=> [
              "summary"=> [
                "start_date"=> $start_date,
                "end_date"=> $end_date,
                "overview"=> "Brief summary of financial performance during the reporting period",
                "Branch" => $shopName,
                "key_metrics"=> [
                  "total_revenue"=> $turnover,
                  "total_expenditure"=> $total_expenditure,
                  "gross_profit"=> $gross_profit,
                  "net_profit"=> $net_profit,
                  "ebit"=> $turnover - ($opex + $marketing_cost + $salaries + $utilities),
                  "roi"=> $total_expenditure > 0 ? ($net_profit / $total_expenditure) * 100 : 0,
                  "profit_margin"=> $net_profit_margin
                ]
              ],
              "revenue"=> [
                "total_revenue"=> $turnover,
                "sales_by_product"=> $sales_by_product,
                "kpi"=> [
                  "average_sales_per_customer"=> $turnover/$customers,
                  "customer_acquisition_cost"=> $marketing_cost + $opex / $customers,
                ]
              ],
              "expenditure"=> [
                "total_expenditure"=> $total_expenditure,
                "cost_of_goods_sold"=> $cogs,
                "operating_expenses"=> $opex + $salaries + $utilities + $marketing_cost,
                "expenditure_details"=> applyShopFilter(Expenditure::whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])
                ->with('type')->with('user'), $shopId)->get(),
                "purchase_details" => $purchase_details
              ],
              "profit_loss_statement"=> [
                "revenue"=> $turnover,
                "cost_of_goods_sold"=> $cogs,
                "gross_profit"=> $gross_profit,
                "operating_expenses"=> $opex + $salaries + $utilities + $marketing_cost,
                "operating_profit"=> $operating_profit,
                "net_profit"=> $net_profit
              ],
              "receivables"=> [
                "total_receivables"=> $receivables_within_period,
                "receivable_details"=> $receivables_within_period_details,
                "expenditure_credit"=> $expenditures_credit,
                "expenditure_part_payment"=> $expenditures_part_payment,
                "purchases_part_credit"=> $purchases_part_credit,
                "purchases_part_payment"=> $purchases_part_payment
              ],
              "stock_movement"=> [
                "stock_sold"=> $stock_sold,
                "stock_adjustments"=> $stock_adjustments,
                "negative_stock"=> $negative_stock_value
              ],
              "cash_flow"=> [
                "cash_inflow"=> $cash_inflow,
                "cash_outflow"=> $cash_outflow
              ],
              "balance_sheet"=> [
                "assets"=> [
                  "current_assets"=> [
                    "cash"=> $cash,
                    "bank"=> $bank,
                    "accounts_receivable"=> $receivables_within_period,
                    "inventory"=> $inventory_value,
                  ],
                ],
                "liabilities"=> [
                  "current_liabilities"=> [
                    "accounts_payable"=> $payables,
                    "liability_details" => $payable_details
                  ],
                ],
                "equity"=> [
                  'owner_equity' => ($cash + $receivables_within_period + $inventory_value) - $payables,
                  "retained_earnings"=> $net_profit,
                ],
              ],
              "logistics_break_down"=> [
                "revenue"=> $logistics,
                "expenditure"=> $logistics_expenditure
              ],
              "sales_vs_marketing_expenditure"=> [
                "sales_expenditure"=> $cogs,
                "marketing_expenditure"=> $marketing_cost
              ],
              "budget_vs_actual"=> [
                "budgeted_revenue"=> $budgeted_revenue,
                "actual_revenue"=> $turnover,
                "revenue_variance"=> $turnover - $budgeted_revenue,
                "budgeted_expenditure"=> $budgeted_expenditure,
                "actual_expenditure"=> $total_expenditure,
                "expenditure_variance"=> $total_expenditure - $budgeted_expenditure,
              ],
              "marketing_details"=> [
                "total_campaign_spend"=> $marketing_cost,
                "customer_acquisition_cost"=> $marketing_cost / $new_customers,
                "return_on_investment"=> $marketing_revenue / $marketing_cost
              ],
              "receivables_ageing"=> [
                "0_30_days"=> $receivables_30_days,
                "31_60_days"=> $receivables_60_days,
                "61_90_days"=> $receivables_90_days,
                "over_90_days"=> $receivables_over_90_days
              ],
              "payables_ageing"=> [
                "0_30_days"=> $payables_30_days,
                "31_60_days"=> $payables_60_days,
                "61_90_days"=> $payables_90_days,
                "over_90_days"=> $payables_over_90_days
              ]
            ]
          ];
          

        return res_success('report generated', $report);
          
    }

public function getSalesByProduct($start_date, $end_date, $shopId)
{
    

    try {


        // Build query
        $query = Sale::select('products.id as product_id', 'products.name as product_name')
            ->selectRaw('SUM(sales.price  * sales.qty) as total_amount')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$start_date, $end_date]);

        // Handle shop_id
        if ($shopId !== 0) {
            $query->where('sales.shop_id', $shopId);
        }

        // Group by product and execute
        $sales_by_product = $query->groupBy('products.id', 'products.name')
            ->orderBy('total_amount', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'total_amount' => (float) $item->total_amount,
                ];
            })
            ->toArray();

        // Return response
        return [
            'sales_by_product' => $sales_by_product,
        ];

    } catch (\Exception $e) {
        return $e->getMessage();
    }
}
}