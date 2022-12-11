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
use App\Models\Products;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(ProductRepositoryInterface $productRepo){
        return $this->productRepo = $productRepo;
    }
    public function create_category(CreateCategoryRequest $request){
        $validated = $request->validated();
        return $this->productRepo->create_category($validated);
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

    public function all_products(){
        $all_products = Products::with('category')->with('images')->get();
        return res_success('all products', $all_products);
    }

    public function general_generate_product_report($id){
        $report = Products::with('category')->with(
            ['sales' => function($q) {
                $q->select('sales.*','users.fullname')->join('users', 'sales.user_id', 'users.id');
            }]
            )->with(
                ['purchases' => function($q) {
                    $q->select('purchase_details.*', 'purchases.user_id','users.fullname')
                        ->join('purchases', 'purchase_details.purchase_id', 'purchases.id')
                        ->join('users', 'purchases.user_id', 'users.id');
                }]
            )->find($id);
        return res_success('report', $report);
    }

    public function all_categories(){
        $all_categories = Category::with('products')->get();
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
            $request['image']->move(public_path('images/products'), $picture);
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

    public function all_purchases(){
        $all =  Purchase::with('user')->with(['purchase_detail' => function($q) {
            $q->join('products', 'purchase_details.product_id', 'products.id');
        }])->get();
        return res_success('all purchases', $all);
    }

    public function purchase_report(Request $request){
        $all =  Purchase::whereBetween(\DB::raw('DATE(`created_at`)'), [$request['start_date'], $request['end_date']])->with('user')->with(['purchase_detail' => function($q) {
            $q->join('products', 'purchase_details.product_id', 'products.id');
        }])->get();
        return res_success('purchase report', $all);
    }


}


