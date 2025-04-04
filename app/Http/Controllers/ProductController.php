<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\CreatePurchaseRequest;
use App\Http\Requests\ProductReportRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Http\Resources\CategoryResource;
use App\Interfaces\ProductRepositoryInterface;
use App\Models\Category;
use App\Models\ProductImages;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public $productRepo;
    public function __construct(ProductRepositoryInterface $productRepo){
         $this->productRepo = $productRepo;
    }
    public function create_category(CreateCategoryRequest $request){
        // $validated = $request->validated();
        return $this->productRepo->create_category($request);
    }
    public function update_category(UpdateCategoryRequest $request, $id){
        $validated = $request->validated();
        return $this->productRepo->update_category($validated, $id);
    }
    public function delete_category($id){
        return $this->productRepo->delete_category($id);
    }

    public function create_product(CreateProductRequest $request){
        $validated = $request->validated();
        return $this->productRepo->create_product($validated);
    }

    public function update_product(UpdateProductRequest $request, $id){
        $validated = $request->validated();
        return $this->productRepo->update_product($validated, $id);
    }

    public function delete_product($id){
        return $this->productRepo->delete_product($id);
    }

    public function generate_product_report(ProductReportRequest $request, $id){
        $validated = $request->validated();
        return $this->productRepo->generate_product_report($validated, $id);
    }

    public function all_Products(Request $request){
        $shopId = $request->query('shop_id');

        $all_Product = applyShopFilter(Product::with('category')->with('images')->orderBy('name', 'ASC'), $shopId)->get();
        return res_success('all products', $all_Product);
    }

    public function general_generate_product_report($id, Request $request){
        $shopId = $request->query('shop_id');

        $report = applyShopFilter(Product::with('category')->with(
            ['sales' => function($q) {
                $q->select('sales.*','users.fullname')->join('users', 'sales.user_id', 'users.id');
            }]
            )->with(
                ['purchases' => function($q) {
                    $q->select('purchase_details.*', 'purchases.user_id','users.fullname')
                        ->join('purchases', 'purchase_details.purchase_id', 'purchases.id')
                        ->join('users', 'purchases.user_id', 'users.id');
                }]
            ), $shopId)->find($id);
        return res_success('report', $report);
    }

    public function all_categories(Request $request){
        $shopId = $request->query('shop_id');

        $all_categories = applyShopFilter(Category::with('products'), $shopId)->get();
        $categories = [];
        foreach ($all_categories as $category) {
            array_push($categories, new CategoryResource($category));
        }
        return res_success('all categories', $categories);
    }

    public function upload_images(Request $request){
        $picture = null;
        if($request['image'] != null){
            $picture = Str::slug($request['product_id'], '-').time().'.'.$request['image']->extension();
            $request['image']->move(public_path('images/product'), $picture);
        }
        $data = ["product_id"=> $request['product_id'], "image" => $picture];

        ProductImages::create($data);

        return res_completed('images stored');
    }

    public function new_purchase(CreatePurchaseRequest $request){
        $validated = $request->validated();
        return $this->productRepo->new_purchase($validated);
    }

    public function update_purchase(Request $request, $id){
        // $validated = $request->validated();
        return $this->productRepo->update_purchase($request, $id);
    }

    public function delete_purchase_detail($id){
        return $this->productRepo->delete_purchase_detail($id);
    }
    public function delete_purchase($id){
        return $this->productRepo->delete_purchase($id);
    }

    public function updatePaymentPlan(Request $request, $id){
        $purchase_detail = PurchaseDetails::find($id);
        $shopId = request()->query('shop_id');

        if ($purchase_detail) {
            $purchase_detail->update([
                "payment_method" => $request["payment_method"],
                "payment_status" => $request["payment_status"],
                "part_payment_amount" => $request["part_payment_amount"],
                "duration" => $request["duration"]
            ]);

            if ($request["payment_status"] == 'paid') {
                bankService(
                    $purchase_detail['cost'] * $purchase_detail['qty'], 
                    "EXPENDITURE COGS - PAID",
                    $purchase_detail->purchase_id,
                    $shopId,
                    "DEBIT"
                );
            }else if ($request['payment_method'] == 'part_payment') {
                bankService(
                    $request['part_payment_amount'], 
                    "EXPENDITURE COGS - PART PAYMENT",
                    $purchase_detail->purchase_id,
                    $shopId,
                    "DEBIT"
                );
            }else{
                bankService(
                    $purchase_detail['cost'] * $purchase_detail['qty'], 
                    "EXPENDITURE COGS - CREDIT",
                    $purchase_detail->purchase_id,
                    $shopId,
                    "DEBIT"
                );
            }
        }

        return res_completed('updated');
    }

    public function all_purchases(Request $request){
        $shopId = $request->query('shop_id');

        $all =  applyShopFilter(Purchase::with('user')->with(['purchase_detail' => function($q) {
                    $q->with('product');
                }]), $shopId)->get();
        return res_success('all purchases', $all);
    }

    public function purchase_report(Request $request){
        $shopId = $request->query('shop_id');

        $all =  applyShopFilter(Purchase::whereBetween(DB::raw('DATE(`created_at`)'), [$request['start_date'], $request['end_date']])
        ->with('user')
        ->with(['purchase_detail' => function($q) {
            $q->join('products', 'purchase_details.product_id', 'products.id');
        }]), $shopId)
        ->get();
        return res_success('purchase report', $all);
    }


}


