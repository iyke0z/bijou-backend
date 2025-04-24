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
use Illuminate\Support\Facades\Log;
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


public function downloadReport($request)
{
    // Validate inputs
    try {
        $start_date = Carbon::parse($request['start_date'])->format('Y-m-d');
        $end_date = Carbon::parse($request['end_date'])->format('Y-m-d');
        if ($start_date > $end_date) {
            Log::error("Invalid date range: start_date ($start_date) is after end_date ($end_date)", ['shopId' => $request['shop_id']]);
            return res_bad_request('Invalid date range');
        }
        $shopId = $request['shop_id'];
        if (empty($shopId) && $shopId !== 0) {
            Log::error("Invalid shopId: shopId is empty");
            return res_bad_request('Invalid shopId');
        }
    } catch (\Exception $e) {
        Log::error("Error parsing dates: {$e->getMessage()}", ['shopId' => $request['shop_id']]);
        return res_bad_request('Invalid date format');
    }

    // Define date range with business hours
    $businessTime = getBusinessTime();
    $opening_time = $businessTime['start_time'] ?? "00:00";
    $closing_time = $businessTime['closing_time'] ?? "23:59";
    $start_date = $start_date . ' ' . $opening_time;
    $end_date = $end_date . ' ' . $closing_time;

    // Get shop name
    $shopName = "All Shops";
    if ($shopId != 0) {
        $shop = Shop::find($shopId);
        $shopName = $shop ? $shop->title : "Unknown Shop";
    }

    // Get ledger records
    $ledger = applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date]), $shopId)->get();

    // Initialize financial metrics
    $turnover = 0;
    $total_expenditure = 0;
    $cogs = 0;
    $gross_profit = 0;
    $net_profit = 0;
    $opex = 0;
    $marketing_expense = 0;
    $salaries = 0;
    $utilities = 0;
    $logistics_expense = 0;
    $inventory_value = 0;
    $payables = 0;
    $receivables = 0;
    $prepaid_sales = 0;
    $prepaid_inventory = 0;
    $prepaid_expense = 0;
    $cash_balance = 0;
    $bank_balance = 0;
    $wallets = 0; // Assuming this is a placeholder for customer deposits


    $expenses = [
        'marketing_expense' => 0,
        'salaries' => 0,
        'utilities' => 0,
        'logistics_expense' => 0,
    ];

    // Process ledger entries
    foreach ($ledger as $entry) {
        $amount = $entry->amount;
        $type = $entry->transaction_type;
        $account = strtolower($entry->account_name);

        switch ($account) {
            case 'sales':
                if ($type === 'credit') {
                    $turnover += $amount;
                }
                break;

            case 'cost_of_goods_sold':
                if ($type === 'debit') {
                    $cogs += $amount;
                    $total_expenditure += $amount;
                }
                break;

            case 'inventory':
                if ($type === 'debit') {
                    $inventory_value += $amount;
                } elseif ($type === 'credit') {
                    $inventory_value -= $amount;
                }
                break;

            case 'cash':
                $cash_balance += ($type === 'debit') ? $amount : -$amount;
                break;

            case 'bank':
                $bank_balance += ($type === 'debit') ? $amount : -$amount;
                break;

            case 'accounts_payable':
                if ($type === 'credit') {
                    $payables += $amount;
                } elseif ($type === 'debit') {
                    $payables -= $amount;
                }
                break;

            case 'accounts_receivable':
                if ($type === 'debit') {
                    $receivables += $amount;
                } elseif ($type === 'credit') {
                    $receivables -= $amount;
                }
                break;

            case 'prepaid_sales':
                if ($type === 'credit') {
                    $prepaid_sales += $amount;
                } elseif ($type === 'debit') {
                    $prepaid_sales -= $amount;
                }
                break;

            case 'prepaid_inventory':
                if ($type === 'debit') {
                    $prepaid_inventory += $amount;
                } elseif ($type === 'credit') {
                    $prepaid_inventory -= $amount;
                }
                break;

            case 'prepaid_expense':
                if ($type === 'debit') {
                    $prepaid_expense += $amount;
                } elseif ($type === 'credit') {
                    $prepaid_expense -= $amount;
                }
                break;
            case 'wallets':
                if ($type === 'debit') {
                    $wallets -= $amount;
                } elseif ($type === 'credit') {
                    $wallets += $amount;
                }
                break;

            case 'marketing_expense':
            case 'salaries':
            case 'utilities':
            case 'logistics_expense':
                if ($type === 'debit') {
                    $expenses[$account] += $amount;
                    $opex += $amount;
                    $total_expenditure += $amount;
                }
                break;
        }
    }

    // Process transactions for ledger summary
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

    // Fetch additional data
    $cash = $this->getCash($start_date, $end_date, $shopId);
    $bank = $this->getBank($start_date, $end_date, $shopId);
    $logistics = $this->getLogisticsRevenue($start_date, $end_date, $shopId);
    $logistics_expenditure = $this->getLogisticsExpenditure($start_date, $end_date, $shopId);
    $sales_by_product = $this->getSalesByProduct($start_date, $end_date, $shopId);
    $sales_details = $this->getSalesDetails($start_date, $end_date, $shopId);   
    $customers = $this->getCustomerCount($start_date, $end_date, $shopId);
    $budgeted_revenue = applyShopFilter(Budget::where('budget_type', 'revenue')->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date]), $shopId)->sum('budget_amount');
    $budgeted_expenditure = applyShopFilter(Budget::where('budget_type', 'expenditure')->whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date]), $shopId)->sum('budget_amount');

    // Calculate key metrics
    $gross_profit = $turnover - $cogs;
    $net_profit = $gross_profit - $opex;
    $net_profit_margin = $turnover > 0 ? ($net_profit / $turnover) * 100 : 0;
    $ebit = $gross_profit - $opex;
    $roi = $total_expenditure > 0 ? ($net_profit / $total_expenditure) * 100 : 0;

    // Additional Balance Sheet items
    $property_plant_equipment = 0;
    $long_term_investments = 0;
    $intangible_assets = 0;
    $long_term_loans = 0;
    $deferred_tax_liability = 0;
    $owner_investment = (($cash + $bank + $receivables + $inventory_value + $prepaid_inventory + $prepaid_expense) - ($payables + $prepaid_sales)) - $net_profit;
    $retained_earnings = $net_profit;
    $dividends = 0;
    

    // Additional Income Statement items
    $other_income = 0;
    $tax_expense = 0;
    $depreciation = 0;
    $amortization = 0;

    // Build report structure
    $report = [
        "summary" => [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "overview" => "Financial performance summary for the reporting period",
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
        "stock_analysis" => $this->getProductStockAnlysis($start_date, $end_date, $shopId),
        "revenue" => [
            "total_revenue" => $turnover,
            "sales_by_product" => $sales_by_product,
            "sales_details" => $sales_details,
            "kpi" => [
                "average_sales_per_customer" => $customers > 0 ? $turnover / $customers : 0,
                "customer_acquisition_cost" => $customers > 0 ? ($marketing_expense + $opex) / $customers : 0,
            ]
        ],
        "expenditure" => [
            "total_expenditure" => $total_expenditure,
            "cost_of_goods_sold" => $cogs,
            "operating_expenses" => $opex,
            "expenditure_details" => $this->getExpenditureDetails($start_date, $end_date, $shopId),
            "purchase_details" => $this->getPurchaseDetails($start_date, $end_date, $shopId)
        ],
        "profit_loss_statement" => [
            "revenue" => $turnover,
            "other_income" => $other_income,
            "cost_of_goods_sold" => $cogs,
            "gross_profit" => $gross_profit,
            "operating_expenses" => $opex,
            "depreciation" => $depreciation,
            "amortization" => $amortization,
            "operating_profit" => $gross_profit - ($opex + $depreciation + $amortization),
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
                    "prepaid_inventory" => $prepaid_inventory,
                    "prepaid_expense" => $prepaid_expense,
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
                    "prepaid_sales" => $prepaid_sales,
                    "customer_deposits" => $wallets, // Assuming this is a placeholder for customer deposits
                ],
                "non_current_liabilities" => [
                    "long_term_loans" => $long_term_loans,
                    "deferred_tax_liability" => $deferred_tax_liability
                ]
            ],
            "equity" => [
                "owner_investment" => $owner_investment,
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
            "marketing_expenditure" => $marketing_expense
        ],
        "budget_vs_actual" => [
            "budgeted_revenue" => $budgeted_revenue,
            "actual_revenue" => $turnover,
            "revenue_variance" => $turnover - $budgeted_revenue,
            "budgeted_expenditure" => $budgeted_expenditure,
            "actual_expenditure" => $total_expenditure,
            "expenditure_variance" => $total_expenditure - $budgeted_expenditure,
        ],
        "ledger_details" => $ledger,
        "general_ledger_summary" => [
            "total_debit" => $total_debit,
            "total_credit" => $total_credit,
            "debit_transactions" => $debit_transactions,
            "credit_transactions" => $credit_transactions
        ],
    ];

    return res_success('Report generated', $report);
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
        ->whereIn('account_name', ['cash', 'bank']) // Cash and bank accounts
        ->where('transaction_type', 'debit'), $shopId // Inflows are debit entries
        )->sum('amount');
    
    }

    // Method to get Cash Outflow
    public function getCashOutflow($start_date, $end_date, $shopId)
    {
        return applyShopFilter(GeneralLedger::whereBetween('created_at', [$start_date, $end_date])
                ->whereIn('account_name', ['cash', 'bank']) // Cash and bank accounts
                ->where('transaction_type', 'credit'), $shopId // Outflows are credit entries
            )->sum('amount');

    }

public function getSalesDetails($start_date, $end_date, $shopId){
    return applyShopFilter(Sale::whereBetween('created_at', [$start_date, $end_date])
        ->with('product')->with('user'), $shopId)->get();
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

public function getProductStockAnlysis($start_date, $end_date, $shopId)
{
   // foreach product get how many was purchased between start date and end date
    // foreach product get how many was sold between start date and end date
        // should be in a table like product_name | purchased | sold | stock_left
    
    $products = applyShopFilter(Product::with('category')->with('images'), $shopId)->get();

    $productStockAnalysis = [];
    foreach ($products as $product) {
        // products purchased 
        $purchases = PurchaseDetails::where('product_id', $product->id)
            ->whereBetween('created_at', [$start_date, $end_date]);
        
        $purchase_qty = $purchases->sum( 'qty');
        // products sold
        $sales = Sale::where('product_id', $product->id)
            ->whereBetween('created_at', [$start_date, $end_date]);
        $sales_qty = $sales->sum( DB::raw('qty'));
        // stock left
        $stock_left = $purchase_qty - $sales_qty;
        $cost_price = $purchase_qty == 0 ? ($purchases->sum(DB::raw('cost * qty')) * $sales_qty) : ($purchases->sum(DB::raw('cost * qty')) * $sales_qty)/$purchase_qty; //for what was sold
        $sales_price = $sales->sum(DB::raw('price * qty'));
        $productStockAnalysis[] = [
            'product_name' => $product->name,
            'purchased' => $purchase_qty,
            'sold' => $sales_qty,
            'stock_left' => $stock_left,
            'cost_price' => $cost_price,
            'cost_price_per_product' => $purchases->sum(DB::raw('cost')),
            'sales_price' => $sales_price,
            'gross_profit' => $sales_price - $cost_price,
        ];
    }  
    return $productStockAnalysis;
}
}