<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\CreateDiscountRequest;
use App\Http\Requests\CreateSalesRequest;
use App\Http\Requests\CustomerDicountRequest;
use App\Http\Requests\PayRequest;
use App\Http\Requests\PeriodicSalesRequest;
use App\Http\Requests\SearchDiscountRequest;
use App\Http\Requests\UpdatePrepStatusRequest;
use App\Http\Requests\UpdateSalesRequest;
use Illuminate\Http\Request;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Sales;
use App\Models\Transaction;
use App\Models\WaiterCode;
use App\Traits\CheckoutTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as FacadesValidator;

use function PHPUnit\Framework\isNull;

class TransactionController extends Controller
{
    use CheckoutTrait;
    public $transRepo;
    public function __construct(TransactionRepositoryInterface $transRepo){
        $this->transRepo = $transRepo;
    }

    public function sell(Request $request){
        // $validated = $request->validated();
        return $this->transRepo->sell($request);
    }
    public function update_sale(UpdateSalesRequest $request){
            $validated = $request->validated();
            return $this->transRepo->update_sale($validated);
        }
    public function delete_sale(CancelOrderRequest $request){
        $validated = $request->validated();
        return $this->transRepo->delete_sale($validated);
    }

    public function payondelivery(Request $request){
        $payod = CheckoutTrait::verify_pay_on_delivery($request['customer_id'], $request['location']);
        return res_completed($payod);
    }

    public function payoncredit(Request $request){
        $payoncredit = CheckoutTrait::verify_on_credit($request['customer_id']);
        return res_completed($payoncredit);
    }

    public function all_sales(){
        $sales = Sales::with('product')->with('discount')->with('user')->get();
        return res_success('all sales', $sales);
    }

    public function periodic_sales(PeriodicSalesRequest $request){
        if($request['platform'] == 'all'){
            $sales = Sales::join('transactions', 'sales.ref', 'transactions.id')
                        ->whereBetween(DB::raw('DATE(sales.`created_at`)'), [$request['start_date'], $request['end_date']])->with('product')
                        ->with('discount')
                        ->with('user')
                        ->get();

            return res_success('all sales', $sales);
        }else{
            $sales = Sales::join('transactions', 'sales.ref', 'transactions.id')
                        ->whereBetween(DB::raw('DATE(sales.`created_at`)'), [$request['start_date'], $request['end_date']])->with('product')
                        ->where('transactions.platform', $request['platform'])
                        ->with('discount')
                        ->with('user')
                        ->get();

            return res_success('all sales', $sales);
        }
    }

    public function sales_report_today(){
        $today = Carbon::now();
        $tomorrow = Carbon::tomorrow();

        $sales = Transaction::whereBetween(DB::raw('DATE(`created_at`)'), [$today->format('Y-m-d'), $tomorrow->format('Y-m-d')])
        ->where('type', '!=', 'credit')
        ->where('type', '!=', 'new_acount')
        ->with('sales')->get();

        $salesOnline = Transaction::whereBetween(DB::raw('DATE(`created_at`)'), [$today->format('Y-m-d'), $tomorrow->format('Y-m-d')])
        ->where('type', '!=', 'credit')
        ->where('type', '!=', 'new_acount')
        ->where('platform', 'online')
        ->with('sales')->get();

        $salesOffline = Transaction::whereBetween(DB::raw('DATE(`created_at`)'), [$today->format('Y-m-d'), $tomorrow->format('Y-m-d')])
        ->where('type', '!=', 'credit')
        ->where('type', '!=', 'new_acount')
        ->where('platform', 'offline')
        ->with('sales')->get();
        return res_success('all transaction', ["all"=>$sales, "online"=>$salesOnline, "offline"=>$salesOffline ]);
    }

    public function create_discount(CreateDiscountRequest $request){
        $validated = $request->validated();
        return $this->transRepo->create_discount($validated);
    }

    public function update_discount($request, $id){
        $validated = $request->validated();
        return $this->transRepo->update_discount($validated, $id);
    }

    public function delete_discount($id){
        return $this->transRepo->delete_discount($id);
    }

    public function customer_discount(CustomerDicountRequest $request, $id){
        $validated = $request->validated();
        return $this->transRepo->customer_discount($validated, $id);
    }

    public function all_discounts(){
        return res_success('discounts', Discount::all());
    }

    public function search_discount(Request $request){
        $validated = FacadesValidator::make($request->all(), ["code"=>'required']);
        if($validated){
            $search = Discount::where('code', $request['code'])->first();
            if($search){
                $discount_zero = $search->count > 0;
                $active_discount = strtotime($search->expiry_date) > time();
                if($discount_zero && $active_discount){
                    return res_success('code exists', $search);
                }else{
                    return res_completed('code unavailable');
                }
            }else{
                return res_not_found('code does not exist');
            }
        }
    }

    public function get_active_orders(Request $request){

        $auth = WaiterCode::where('code', $request['auth_code'])->first();
        if($auth){
            // $orders = Transaction::join('sales', 'transactions.id', 'sales.ref')
            // ->where('sales.user_id', $auth['user_id'])
            // ->where("sales.prep_status", "not_ready")
            // ->OrWhere("sales.prep_status", "almost_ready")
            // ->with('sales')
            // ->get();

            $orders = Transaction::with(['sales'=> function($q){
                $q->with('product');
            }])
            ->with('user')->where('status', 'pending')->where('user_id', $auth['user_id'])->get();


            return res_success('orders', $orders);
        }

        return res_unauthorized('Unauthorized');


    }
    public function pay(PayRequest $request){
        $validated = $request->validated();

        return $this->transRepo->pay($validated);
    }

    public function delete_customer_discount($id, $discount){
        return $this->transRepo->delete_customer_discount($id, $discount);
    }

    public function drinks_prep_status(){
        return $this->transRepo->drinks_prep_status();
    }

    public function food_prep_status(){
        return $this->transRepo->food_prep_status();
    }

    public function update_prep_status(UpdatePrepStatusRequest $request){
        $validated = $request->validated();

        return $this->transRepo->update_prep_status($validated);
    }
}
