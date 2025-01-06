<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Http\Request;
use App\Interfaces\CustomerRepositoryInterface;
use App\Models\Customer;

class CustomerController extends Controller
{
    public $customerRepo;
    public function __construct(CustomerRepositoryInterface $customerRepo){
        $this->customerRepo = $customerRepo;
    }
    public function create_customer(CreateCustomerRequest $request){
        $validated = $request->validated();
        return $this->customerRepo->create_customer($validated);
    }
    public function update_customer(UpdateCustomerRequest $request, $id){
        $validated = $request->validated();
        return $this->customerRepo->update_customer($validated, $id);
    }
    public function fund_customer(Request $request, $id){
        return $this->customerRepo->fund_customer($request, $id);
    }
    public function delete_customer($id){
        return $this->customerRepo->delete_customer($id);
    }
    public function customer_details($id){
        return $this->customerRepo->customer_details($id);
    }
    public function all_customers(Request $request){
        $shopId = $request->query('shop_id');

        $all = applyShopFilter(Customer::with('transactions'), $shopId)->get();
        return res_success('customers', $all);
    }
}
