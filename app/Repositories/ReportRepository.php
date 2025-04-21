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
        return Customer::all()->count(); // Assuming you want the total count of customers
    }

    public function getExpenditureDetails($start_date, $end_date, $shopId) {
        return applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])
            ->with('exp_type')->with('user'), $shopId)->get();
    }

    public function getPurchaseDetails($start_date, $end_date, $shopId) {
        return applyShopFilter(PurchaseDetails::with('purchase.user')->with('purchase.documents')->with('product')->whereBetween('created_at', [$start_date, $end_date]), $shopId)->get();
    }

    public function getReceivablesDetails($start_date, $end_date, $shopId) {
        return applyShopFilter(
            Customer::where('wallet_balance', '<', 0),
            $shopId
        )->get();
        
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
        $turnover = ;
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
        $receivables = 0;
        
        
        $expenses = [
            'marketing_cost' => 0,
            'salaries' => 0,
            'utilities' => 0,
        ];
        
        $cash_balance = 0;
        $bank_balance = 0;
        
        foreach ($ledger as $entry) {
            $amount = $entry->amount;
            $type = $entry->transaction_type;
            $account = strtolower($entry->account_name);
        
            switch ($account) {
                case 'sales':
                    if ($type === 'credit') {
                        $turnover += $amount;
        
                        // Check for matching cash/bank debit (full payment)
                        $paid = $ledger->contains(function ($e) use ($amount) {
                            return in_array(strtolower($e->account_name), ['cash', 'bank']) &&
                                $e->transaction_type === 'debit' &&
                                $e->amount === $amount;
                        });
        
                        if (!$paid) {
                            $receivables += $amount;
                        }
                    }
                    break;
        
                case 'cost_of_goods_sold':
                    if ($type === 'debit') {
                        $cogs += $amount;
                    }
                    break;
        
                case 'inventory':
                    if ($type === 'debit') {
                        $inventory_value += $amount;
                    }
                    break;
        
                case 'cash':
                    $cash_balance += ($type === 'debit') ? $amount : -$amount;
                    break;
        
                case 'bank':
                    $bank_balance += ($type === 'debit') ? $amount : -$amount;
                    break;
        
                case 'purchase':
                    if ($type === 'debit') {
                        $total_expenditure += $amount;
                    }
                    break;
        
                case 'accounts_payable':
                    if ($type === 'credit') {
                        $payables += $amount;
                    }
                    break;
        
                case 'accounts_receivable':
                    if ($type === 'debit') {
                        $receivables += $amount;
                    }
                    break;
        
                // Expenses
                case 'marketing_cost':
                case 'salaries':
                case 'utilities':
                    if ($type === 'credit') {
                        $expenses[$account] += $amount;
                        $total_expenditure += $amount;
                    }
                    break;
            }
        }
        
        

        $debit_transactions = [];
        $credit_transactions = [];
        $total_debit = 0;
        $total_credit = 0;

        foreach ($ledger as $entry) {
            $transaction = [
                'account_name' => $entry->account_name,
                'amount' => $entry->amount,
                'description' => $entry->description ?? '',
                'transaction_id' => $entry->transaction_id ?? '',
                'transaction_type' => $entry->transaction_type,
                'date' => $entry->created_at->format('Y-m-d H:i:s'),
            ];

            if ($entry->transaction_type === 'debit') {
                $debit_transactions[] = $transaction;
                $total_debit += $entry->amount;
            } elseif ($entry->transaction_type === 'credit') {
                $credit_transactions[] = $transaction;
                $total_credit += $entry->amount;
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
        // Non-current assets (assuming you don't track these yet, so initializing to 0)
        $property_plant_equipment = 0;
        $long_term_investments = 0;
        $intangible_assets = 0;

        // Long-term liabilities
        $long_term_loans = 0;
        $deferred_tax_liability = 0;

        // Equity (extending for more detailed items)
        $owner_investment = 0;
        $retained_earnings = $net_profit;
        $dividends = 0;

        // Additional Income Statement items
        $other_income = 0;
        $tax_expense = 0;
        $depreciation = 0;
        $amortization = 0;

        if($turnover == 0){
            $turnover = 0.01; // Avoid division by zero in ROI calculation
        }
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
                "other_income" => $other_income,
                "cost_of_goods_sold" => $cogs,
                "gross_profit" => $gross_profit,
                "operating_expenses" => $opex + $salaries + $utilities + $marketing_cost,
                "depreciation" => $depreciation,
                "amortization" => $amortization,
                "operating_profit" => $gross_profit - ($opex + $marketing_cost + $salaries + $utilities + $depreciation + $amortization),
                "tax_expense" => $tax_expense,
                "net_profit" => $net_profit
            ],

            "receivables" => [
                "total_receivables" => $receivables,
                "receivable_details" => $this->getReceivablesDetails($start_date, $end_date, $shopId),
                "customer_count" => $customers,
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
                    "accounts_receivable" => $receivables,
                    "inventory" => $inventory_value,
                ],
                "non_current_assets" => [
                    "property_plant_equipment" => $property_plant_equipment,
                    "long_term_investments" => $long_term_investments,
                    "intangible_assets" => $intangible_assets
                ]
            ],
            "liabilities" => [
                "current_liabilities" => [
                    "accounts_payable" => $payables,
                ],
                "non_current_liabilities" => [
                    "long_term_loans" => $long_term_loans,
                    "deferred_tax_liability" => $deferred_tax_liability
                ]
            ],
            "equity" => [
                "owner_investment" => (($cash + $receivables + $inventory_value) - $payables) - $net_profit,
                "retained_earnings" => $retained_earnings,
                "dividends" => $dividends
            ]
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
            "general_ledger_summary" => [
                "total_debit" => $total_debit,
                "total_credit" => $total_credit,
                "debit_transactions" => $debit_transactions,
                "credit_transactions" => $credit_transactions
            ],

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