<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Banks;
use App\Models\BusinessDetails;
use App\Models\Customer;
use App\Models\Expenditure;
use App\Models\Liquidity;
use App\Models\LogisticsAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use App\Models\Sale;
use App\Models\Sales;
use App\Models\SplitPayments;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class BackendController extends Controller
{
    public $opening_time;
    public $closing_time;
    public $shopId;
    public function __construct(){
        $businessTime = getBusinessTime();
        $this->opening_time = $businessTime['start_time'] ?? "00:00";
        $this->closing_time = $businessTime['closing_time'] ?? "23:59";
    }
    public function sales(Request $request){
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
                                        ->with('purchase')->with('product')->withTrashed(), $shopId)->get();
    
                    $get_expenditures = applyShopFilter(Expenditure::whereBetween('created_at', [$start_date, $end_date])
                                        ->with('type')->with('user')->withTrashed(), $shopId)->get();
    
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
    public function purchases(Request $request){
        $shopId = $request->query('shop_id');

        $all =  applyShopFilter(Purchase::with('user')->with('documents')->with(['purchase_detail' => function($q) {
                    $q->with('product');
                }])->withTrashed(), $shopId)->get();
        
        foreach ($all as $purchase) {
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
        return res_success('all purchases', $all);
    }
    public function products(Request $request){
        $shopId = $request->query('shop_id');

        $all_Product = applyShopFilter(Product::with('category')->with('images')->orderBy('name', 'ASC')->withTrashed(), $shopId)->get();
        return res_success('all products', $all_Product);
    }
    public function expenditure(Request $request){ 
        $shopId = $request->query('shop_id');

        return res_success('all', applyShopFilter(Expenditure::with('type')->with('documents')->with('user')->withTrashed(), $shopId)->get());
    }
    public function staff(Request $request){
        $shopId = $request->query('shop_id');
    
        $users = applyShopFilter(
            User::with(['role', 'shop', 'shop_access', 'access_code'])->withTrashed(),
            $shopId
        )->get();
    
        return res_success('users', $users);
    }
    public function customer(Request $request){
        $shopId = $request->query('shop_id');

        $all = applyShopFilter(Customer::with('transactions')->withTrashed(), $shopId)->get();
        return res_success('customers', $all);
    }

}
