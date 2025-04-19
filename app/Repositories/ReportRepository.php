<?php

namespace App\Repositories;

use App\Interfaces\ReportRepositoryInterface;
use App\Models\Budget;
use App\Models\BusinessDetails;
use App\Models\Customer;
use App\Models\Expenditure;
use App\Models\GeneralLedger;
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

    public function getCustomerCount($start_date, $end_date, $shopId) {
        return applyShopFilter(Customer::whereBetween('created_at', [$start_date, $end_date]), $shopId)->count();
    }

    public function getExpenditureDetails($start_date, $end_date, $shopId) {
        return applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])
            ->with('exp_type')->with('user'), $shopId)->get();
    }

    public function getPurchaseDetails($start_date, $end_date, $shopId) {
        return applyShopFilter(Purchase::with('user')->with('documents')->with(['purchase_detail' => function($q) {
            $q->with('product');
        }])->whereBetween('created_at', [$start_date, $end_date]), $shopId)->get();
    }

    public function getReceivablesDetails($start_date, $end_date, $shopId) {
        return applyShopFilter(Customer::select(DB::raw('fullname') , DB::raw('ABS(wallet_balance) as wallet_balance'))
            ->where('wallet_balance', "<", 0), $shopId)->get();
    }

    // Method to get Cash Value
    public function getCash($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'cash'), $shopId // Assuming cash is tracked in the 'cash' account
            )->sum('amount'); // Sum of cash transactions (credits and debits)
    }

    // Method to get Bank Value
    public function getBank($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'bank'), $shopId // Assuming bank transactions are tracked in 'bank' account
            )->sum('amount');
    }

    // Method to get Accounts Receivable
    public function getAccountsReceivable($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'accounts_receivable') // Assuming AR is tracked in 'accounts_receivable' account
            , $shopId)->sum('amount');
    }

        // Method to get Inventory Value
    // Method to get Logistics Revenue
public function getLogisticsRevenue($start_date, $end_date, $shopId)
{
    return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
        ->where('account_name', 'logistics')
        ->where('transaction_type', 'credit'), $shopId)->sum('amount');
}

// Method to get Logistics Expenditure
public function getLogisticsExpenditure($start_date, $end_date, $shopId)
{
    return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'logistics') 
            ->where('transaction_type', 'debit'), $shopId)->sum('amount');
}

    public function downloadReport($request) {
        // Define your date range
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
        // Get all ledger records within the date range for the specific shop
        $ledger = applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date]), $shopId)->get();

        // Initialize variables for calculations
        $turnover = 0;
        $total_expenditure = 0;
        $cogs = 0;
        $gross_profit = 0;
        $net_profit = 0;
        $opex = 0;
        $marketing_cost = 0;
        $salaries = 0;
        $utilities = 0;
        $inventory_value = 0;
        $payables = 0;
        $receivables_within_period = 0;
        
        // Iterate through the ledger to calculate necessary values
        foreach ($ledger as $entry) {
            $amount = $entry->amount;
    
            switch ($entry->account_name) {
                case 'sales':
                    if ($entry->transaction_type == 'credit') {
                        $turnover += $amount;
                    }
                    break;
    
                case 'cost_of_goods_sold':
                    if ($entry->transaction_type == 'credit') {
                        $cogs += $amount;
                    }
                    break;
    
                case 'cash':
                case 'bank':
                    if ($entry->transaction_type == 'debit') {
                        $total_expenditure += $amount;
                    } else {
                        $receivables_within_period += $amount;
                    }
                    break;
    
                case 'purchase':
                    if ($entry->transaction_type == 'debit') {
                        $total_expenditure += $amount;
                    }
                    break;
    
                case 'inventory':
                    if ($entry->transaction_type == 'debit') {
                        $inventory_value += $amount;
                    }
                    break;
    
                case 'accounts_payable':
                    if ($entry->transaction_type == 'credit') {
                        $payables += $amount;
                    }
                    break;
    
                // Other expense accounts
                case 'marketing_cost':
                    if ($entry->transaction_type == 'credit') {
                        $marketing_cost += $amount;
                    }
                    break;
    
                case 'salaries':
                    if ($entry->transaction_type == 'credit') {
                        $salaries += $amount;
                    }
                    break;
    
                case 'utilities':
                    if ($entry->transaction_type == 'credit') {
                        $utilities += $amount;
                    }
                    break;
            }

        }

        $cash = $this->getCash($start_date, $end_date, $shopId);
        $bank = $this->getBank($start_date, $end_date,  $shopId);
        $logistics = $this->getLogisticsRevenue($start_date, $end_date, $shopId);
        $logistics_expenditure = $this->getLogisticsExpenditure($start_date, $end_date, $shopId);
        // Calculate key metrics
        $gross_profit = $turnover - $cogs;
        $net_profit = $gross_profit - ($opex + $marketing_cost + $salaries + $utilities);
        $total_expenditure = $cogs + $marketing_cost + $salaries + $utilities + $inventory_value;
    
        // Profit margin calculation
        $net_profit_margin = ($net_profit / $turnover) * 100;
    
        // Calculate EBIT and ROI
        $ebit = $turnover - ($opex + $marketing_cost + $salaries + $utilities);
        $roi = $total_expenditure > 0 ? ($net_profit / $total_expenditure) * 100 : 0;

    
        // Fetch sales by product, customers, receivables, etc.
        // Example: You may have a relationship or additional tables to fetch these dynamically
        $sales_by_product = $this->getSalesByProduct($start_date, $end_date, $shopId);
        $customers = $this->getCustomerCount($start_date, $end_date, $shopId);
        $budgeted_revenue = applyShopFilter(Budget::where('budget_type', 'revenue')->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date]), $shopId)
        ->sum('budget_amount');
$budgeted_expenditure = applyShopFilter(Budget::where('budget_type', 'expenditure')->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date]), $shopId)
->sum('budget_amount');
        // Build the report structure
        $report = [
            "summary" => [
                "start_date" => $start_date,
                "end_date" => $end_date,
                "overview" => "Brief summary of financial performance during the reporting period",
                "Branch" => $shopName,
                "key_metrics" => [
                    "total_revenue" => $turnover,
                    "total_expenditure" => $total_expenditure,
                    "gross_profit" => $gross_profit,
                    "net_profit" => $net_profit,
                    "ebit" => $ebit,
                    "roi" => $roi,
                    "profit_margin" => $net_profit_margin
                ]
            ],
            "revenue" => [
                "total_revenue" => $turnover,
                "sales_by_product" => $sales_by_product,
                "kpi" => [
                    "average_sales_per_customer" => $turnover / $customers,
                    "customer_acquisition_cost" => ($marketing_cost + $opex) / $customers,
                ]
            ],
            "expenditure" => [
                "total_expenditure" => $total_expenditure,
                "cost_of_goods_sold" => $cogs,
                "operating_expenses" => $opex + $salaries + $utilities + $marketing_cost,
                "expenditure_details" => $this->getExpenditureDetails($start_date, $end_date, $shopId),
                "purchase_details" => $this->getPurchaseDetails($start_date, $end_date, $shopId)
            ],
            "profit_loss_statement" => [
                "revenue" => $turnover,
                "cost_of_goods_sold" => $cogs,
                "gross_profit" => $gross_profit,
                "operating_expenses" => $opex + $salaries + $utilities + $marketing_cost,
                "operating_profit" => $gross_profit - ($opex + $marketing_cost + $salaries + $utilities),
                "net_profit" => $net_profit
            ],
            "receivables" => [
                "total_receivables" => $receivables_within_period,
                "receivable_details" => $this->getReceivablesDetails($start_date, $end_date, $shopId)
            ],
            "stock_movement" => [
                "stock_sold" => $this->getStockSold($start_date, $end_date, $shopId),
                "stock_adjustments" => $this->getStockAdjustments($start_date, $end_date, $shopId),
                "negative_stock" => $this->getNegativeStockValue($start_date, $end_date, $shopId)
            ],
            "cash_flow" => [
                "cash_inflow" => $this->getCashInflow($start_date, $end_date, $shopId),
                "cash_outflow" => $this->getCashOutflow($start_date, $end_date, $shopId)
            ],
            "balance_sheet" => [
                "assets" => [
                    "current_assets" => [
                        "cash" => $cash,
                        "bank" => $bank,
                        "accounts_receivable" => $receivables_within_period,
                        "inventory" => $inventory_value,
                    ],
                ],
                "liabilities" => [
                    "current_liabilities" => [
                        "accounts_payable" => $payables,
                    ],
                ],
                "equity" => [
                    "owner_equity" => (($cash + $receivables_within_period + $inventory_value) - $payables) ,
                    "retained_earnings" => $net_profit,
                ],

            ],
            "logistics_break_down" => [
                "revenue" => $logistics,
                "expenditure" => $logistics_expenditure
            ],
            "sales_vs_marketing_expenditure" => [
                "sales_expenditure" => $cogs,
                "marketing_expenditure" => $marketing_cost
            ],
            "budget_vs_actual" => [
                "budgeted_revenue" => $budgeted_revenue,
                "actual_revenue" => $turnover,
                "revenue_variance" => $turnover - $budgeted_revenue,
                "budgeted_expenditure" => $budgeted_expenditure,
                "actual_expenditure" => $total_expenditure,
                "expenditure_variance" => $total_expenditure - $budgeted_expenditure,
            ],
            "ledger_details"=> $ledger,
        ];
    
        return res_success('report generated', $report);
    }
    
    

    public function getStockSold($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'inventory') // Assuming inventory is the account tracking stock
            ->where('transaction_type', 'credit'), $shopId // Stock sold (inventory out)
            )->sum('amount'); // Sum of the amount for stock sold
    }

    // Method to get Stock Adjustments
    public function getStockAdjustments($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'inventory') // Assuming inventory adjustments are tracked here
            ->whereIn('transaction_type', ['debit', 'credit']), $shopId // Both debit and credit can represent adjustments
            )->sum('amount'); // Sum of the adjustment amounts
    }

    // Method to get Negative Stock Value
    public function getNegativeStockValue($start_date, $end_date, $shopId)
    {
        // Get the total value of inventory in the specified date range
        $total_inventory = applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'inventory'), $shopId
            )->sum('amount');
        
        // If inventory value is less than 0, consider it negative stock
        return $total_inventory < 0 ? $total_inventory : 0; 
    }

    // Method to get Cash Inflow
    public function getCashInflow($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'cash') // Assuming cash is tracked in a 'cash' account
            ->where('transaction_type', 'credit'), $shopId // Cash inflows are credit transactions
            )->sum('amount'); // Sum of cash inflows
    }

    // Method to get Cash Outflow
    public function getCashOutflow($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
            ->where('account_name', 'cash') // Cash account
            ->where('transaction_type', 'debit'), $shopId // Cash outflows are debit transactions
            )->sum('amount'); // Sum of cash outflows
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