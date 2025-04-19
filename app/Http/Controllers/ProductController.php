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
use App\Models\PurchaseSupportingDocument;
use App\Models\SplitPayments;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

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
                $q->select('sales.*','users.fullname')->join('users', 'sales.user_id', 'users.id')->withTrashed();
            }]
            )->with(
                ['purchases' => function($q) {
                    $q->select('purchase_details.*', 'purchases.user_id','users.fullname')
                        ->join('purchases', 'purchase_details.purchase_id', 'purchases.id')
                        ->join('users', 'purchases.user_id', 'users.id');
                }]
            )->with('transferHistory'), $shopId)->find($id);
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
        $purchase_detail = PurchaseDetails::where('id', $id)->first();
        $shopId = request()->query('shop_id');
        $request["payment_status"] = "not_paid";


        if($request["type"] == 'part_payment') {
            $request["payment_status"] = "not_paid";
        }else if($request["type"] == 'on_credit') {
            $request["payment_status"] = "not_paid";
        }else{
            $request["payment_status"] = "paid";
        }

        if ($purchase_detail) {
            $purchase_detail->update([
                "payment_method" => $request["payment_method"],
                "payment_status" => $request["payment_status"],
                "part_payment_amount" => $request["part_payment_amount"],
                "duration" => $request["duration"],
                "type" => $request["type"],
            ]);

            if ($request["payment_status"] == 'paid') {
                bankService(
                    $purchase_detail['cost'] * $purchase_detail['qty'], 
                    "EXPENDITURE COGS - PAID",
                    $purchase_detail->purchase_id,
                    $shopId,
                    "DEBIT"
                );
            }else if ($request['type'] == 'part_payment') {
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
            if ($request['is_split_payment'] == 1) {
                foreach ($request["split"] as $split) {
                    // store split values
                    $split_payment = new SplitPayments();
                    $split_payment->transaction_id = $purchase_detail->id;
                    $split_payment->payment_method = $split["split_playment_method"];
                    $split_payment->amount = $split["split_payment_amount"];                    
                    $split_payment->shop_id = $shopId;
                    $split_payment->transaction_type = 'purchases';
                    $split_payment->save();

                    
                    
                    registerLedger(
                        'purchase', 
                        $purchase_detail->id, 
                        floatval($split['split_payment_amount']),  //$shopId
                        $shopId, 
                        $request['type'], 
                        $request['payment_method'], 
                        0, 
                        floatval($split['split_playment_method']),
                        getCostPrice($purchase_detail["product_id"])
                    );
                }
            }else{
                if($request['type'] == 'part_payment') {
                    $amount = $request['part_payment_amount'];
                    
                    registerLedger(
                        'purchase', 
                        $purchase_detail->id, 
                        $purchase_detail['cost'] * $purchase_detail['qty'],  //$shopId
                        $shopId, 
                        $request['type'], 
                        $request['payment_method'], 
                        0, 
                        floatval($request['part_payment_amount']),
                        getCostPrice($purchase_detail["product_id"])
                    );
                }else{
                    registerLedger(
                        'purchase', 
                        $purchase_detail->id, 
                        $purchase_detail['cost'] * $purchase_detail['qty'],  //$shopId
                        $shopId, 
                        $request['type'], 
                        $request['payment_method'], 
                        0, 
                        0,
                        getCostPrice($purchase_detail["product_id"])
                    );
                }
            }
        }
        if($purchase_detail){
            $purchase_detailCount = PurchaseDetails::where('purchase_id', $purchase_detail->purchase_id)->count();
            $added_cost = $request['added_cost'] ?? 0;
            registerLedger(
                'purchases', 
                'cogs', 
                ($added_cost/$purchase_detailCount) + $purchase_detail['cost'] * $purchase_detail['qty'], 
                $purchase_detail->purchase_id, 
                $shopId, 
                0, 
                $request['payment_method'], 
                $request['part_payment_amount'] ?? 0);
        }

        return res_completed('updated');
    }

    public function all_purchases(Request $request){
        $shopId = $request->query('shop_id');

        $all =  applyShopFilter(Purchase::with('user')->with('documents')->with(['purchase_detail' => function($q) {
                    $q->with('product');
                }]), $shopId)->get();
        
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

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string',
            'purchase_id' => 'required|integer|exists:purchases,id',
            'files.*' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
        ]);
        

        $documents = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('purchase_documents');
        
            $documents[] = PurchaseSupportingDocument::create([
                'document_type' => $request->document_type,
                'path' => $path,
                'purchase_id' => $request->purchase_id,
            ]);
        }
        
        return response()->json([
            'message' => 'Documents uploaded successfully.',
            'documents' => $documents,
        ], 201);
        
    }

    public function deleteDocument(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:purchase_supporting_documents,id',
        ]);

        $document = PurchaseSupportingDocument::findOrFail($request->id);

        // Delete the file from storage
        Storage::delete($document->path);

        // Soft delete the DB record
        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }
    public function downloadDocuments($id)
    {
        $documents = PurchaseSupportingDocument::where('purchase_id', $id)->get();
    
        if ($documents->isEmpty()) {
            return response()->json(['error' => 'No documents found.'], 404);
        }
    
        // Create a temporary ZIP file
        $zipFileName = 'purchase_documents_' . $id . '.zip';
        $zipPath = storage_path('app/tmp/' . $zipFileName);
    
        // Ensure the tmp directory exists
        if (!file_exists(storage_path('app/tmp'))) {
            mkdir(storage_path('app/tmp'), 0777, true);
        }
    
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($documents->groupBy('document_type') as $type => $groupedDocs) {
                foreach ($groupedDocs as $doc) {
                    if (Storage::exists($doc->path)) {
                        // Add file to ZIP with folder structure by document_type
                        $relativePath = "$type/" . basename($doc->path);
                        $zip->addFile(storage_path('app/' . $doc->path), $relativePath);
                    }
                }
            }
            $zip->close();
        } else {
            return response()->json(['error' => 'Failed to create ZIP file.'], 500);
        }
    
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }


}


