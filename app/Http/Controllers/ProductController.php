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
use App\Models\BooksDetail;
use App\Models\CarsDetail;
use App\Models\Category;
use App\Models\ClothingShoesDetail;
use App\Models\ProductImages;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use App\Models\PurchaseSupportingDocument;
use App\Models\RealEstateDetail;
use App\Models\SplitPayments;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

    public function all_categories(Request $request)
    {
        $shopId = $request->query('shop_id');
    
        $all_categories = applyShopFilter(
            Category::with('products')->orderBy('name'), 
            $shopId
        )->get();
    
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
        $data = ["product_id"=> $request['product_id'], "image" => $picture, "title" => $request['title']];

        ProductImages::create($data);

        return res_completed('images stored');
    }

    public function delete_image($id){
        $image = ProductImages::find($id);
        if ($image) {
            $imagePath = public_path('images/product/' . $image->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
            $image->delete();
            return res_completed('deleted');
        } else {
            return res_not_found('Image not found');
        }
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
        $purchase_detailCount = PurchaseDetails::where('purchase_id', $purchase_detail->purchase_id)->count();
        $added_cost = $request['added_cost'] ?? 0;

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
            //split payments
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
                        "purch_".$purchase_detail->id, 
                        floatval($split['split_payment_amount']) + ($added_cost/$purchase_detailCount),  //$shopId
                        $shopId, 
                        $request['type'], 
                        $request['payment_method'], 
                        0, 
                        floatval($split['split_playment_method']),
                        getCostPrice($purchase_detail["product_id"], $purchase_detail['qty'])
                    );
                }
            }
            // part payment
            else if($request['type'] == 'part_payment') {                    
                    registerLedger(
                        'purchase', 
                        "purch_".$purchase_detail->id, 
                        ($added_cost/$purchase_detailCount)  + $purchase_detail['cost'] * $purchase_detail['qty'],  //$shopId
                        $shopId, 
                        $request['type'], 
                        $request['payment_method'], 
                        0, 
                        floatval($request['part_payment_amount']),
                        getCostPrice($purchase_detail["product_id"], $request['qty'])
                    );
            } else if($request['payment_type']  == 'prepayment' || $request['payment_type'] == 'postpayment'){
                // update transaction
                $purchase_detail = PurchaseDetails::where('id', $purchase_detail->id)->first();
                $purchase_detail->start_date = $request["start_date"];
                $purchase_detail->end_date = $request["end_date"];
                $purchase_detail->payment_type = $request["payment_type"];
                $purchase_detail->monthly_value = (($added_cost/$purchase_detailCount)  + $purchase_detail['cost'] * $purchase_detail['qty'])/Carbon::parse($request['start_date'])->diffInMonths(Carbon::parse($request['end_date']));
                $purchase_detail->posting_day = $request["posting_day"];
                $purchase_detail->save();

                // register ledger
                registerLedger(
                    'purchase',
                    'purch_'.$purchase_detail->id,
                    ($added_cost/$purchase_detailCount)  + $purchase_detail['cost'] * $purchase_detail['qty'] ,
                    $shopId,
                    $request['payment_type'],
                    $request['payment_method'],
                    $request['logistics'] ?? 0,
                    0, // part_payment_amount (already handled)
                    getCostPrice($purchase_detail["product_id"], $purchase_detail['qty']),
                );

            }else {
                registerLedger(
                    'purchase', 
                    "purch_".$purchase_detail->id, 
                    ($added_cost/$purchase_detailCount)  + $purchase_detail['cost'] * $purchase_detail['qty'],  //$shopId
                    $shopId, 
                    $request['type'], 
                    $request['payment_method'], 
                    0, 
                    floatval($request['part_payment_amount']),
                    getCostPrice($purchase_detail["product_id"], $purchase_detail['qty'])
                );

            }
            
            
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

        public function updateProductDetail($id, Request $request){
            // Validate request
            $validator = Validator::make($request->all(), [
                'sizes' => 'nullable|array',
                'colors' => 'nullable|array',
                'material' => 'nullable|string|max:100',
                'style' => 'nullable|string|max:50',
                'author' => 'nullable|string|max:100',
                'isbn' => 'nullable|string|max:20',
                'genre' => 'nullable|string|max:50',
                'publication_date' => 'nullable|date',
                'format' => 'nullable|array',
                'location' => 'nullable|string|max:255',
                'type' => 'nullable|string|max:50',
                'bedrooms' => 'nullable|integer|min:0',
                'bathrooms' => 'nullable|integer|min:0',
                'square_footage' => 'nullable|integer|min:0',
                'contact_email' => 'nullable|email|max:100',
                'contact_phone' => 'nullable|string|max:20',
                'make' => 'nullable|string|max:50',
                'model' => 'nullable|string|max:50',
                'year' => 'nullable|integer|min:1900|max:'.(date('Y') + 1),
                'mileage' => 'nullable|integer|min:0',
                'fuel_type' => 'nullable|string|max:20',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            try {
                // Start transaction
                DB::beginTransaction();
    
                // Fetch product
                $product = Product::find($id);
                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found'
                    ], 404);
                }
    
                // Fetch category
                $category = Category::where('id', $product->category_id)->first();
                if (!$category) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Category not found'
                    ], 404);
                }
    
                $detail = null;

                // Update based on category
                switch ($category->name) {
                    case 'clothing':
                    case 'shoes':
                        $existingDetail = ClothingShoesDetail::where('product_id', $id)->first();
                        if ($existingDetail) {
                            $existingDetail->delete();
                        }
                        $detail = ClothingShoesDetail::updateOrCreate(
                            [
                                'product_id' => $id,
                                'sizes' => implode(',', $request->input('sizes') ),
                                'colors' => implode(',', $request->input('colors')),
                                'material' => $request->input('material'),
                                'style' => $request->input('style')
                            ]
                        );
                        break;
    
                    case 'books':
                        $existingDetail = BooksDetail::where('product_id', $id)->first();
                        if ($existingDetail) {
                            $existingDetail->delete();
                        }
                        $detail = BooksDetail::updateOrCreate(
                            ['product_id' => $id,
                                'author' => $request->input('author'),
                                'isbn' => $request->input('isbn'),
                                'genre' => $request->input('genre'),
                                'publication_date' => $request->input('publication_date'),
                                'format' => implode(',', $request->input('format'))
                            ]
                        );
                        break;
    
                    case 'real_estate':
                        $existingDetail = RealEstateDetail::where('product_id', $id)->first();
                        if ($existingDetail) {
                            $existingDetail->delete();
                        }
                        $detail = RealEstateDetail::updateOrCreate(
                            ['product_id' => $id,
                                'location' => $request->input('location'),
                                'type' => $request->input('type'),
                                'bedrooms' => $request->input('bedrooms'),
                                'bathrooms' => $request->input('bathrooms'),
                                'square_footage' => $request->input('square_footage'),
                                'contact_email' => $request->input('contact_email'),
                                'contact_phone' => $request->input('contact_phone')
                            ]
                        );
                        break;
    
                    case 'cars':
                        $existingDetail = CarsDetail::where('product_id', $id)->first();
                        if ($existingDetail) {
                            $existingDetail->delete();
                        }
                        $detail = CarsDetail::updateOrCreate(
                            ['product_id' => $id,
                                'make' => $request->input('make'),
                                'model' => $request->input('model'),
                                'year' => $request->input('year'),
                                'mileage' => $request->input('mileage'),
                                'fuel_type' => $request->input('fuel_type'),
                                'contact_email' => $request->input('contact_email'),
                                'contact_phone' => $request->input('contact_phone')
                            ]
                        );
                        break;
    
                    default:
                        $existingDetail = Product::where('id', $id)->first();
                        if ($existingDetail) {
                            $existingDetail->delete();
                        }
                        $product->details = [
                            'material' => $request->input('material')
                        ];
                        $product->save();
                        $detail = $product;
                        break;
                }
    
                DB::commit();
    
                return response()->json([
                    'success' => true,
                    'message' => 'Product details updated successfully',
                    'data' => $detail
                ], 200);
    
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update product details',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    
        /**
         * Fetch products for website (shop_id = 1) with adjusted shape based on category.
         *
         * @return \Illuminate\Http\JsonResponse
         */
        public function fetchWebsiteProducts()
        {
                // Fetch products with relationships
                $products = Product::with(['category', 'images', 'clothingShoesDetail', 'booksDetail', 'realEstateDetail', 'carsDetail'])
                    ->where('shop_id', 1)
                    ->where('show_on_website', 1)
                    ->get();
                // return response()->json($products);
                $response = [];
                
                foreach ($products as $product) {
                    $category = $product->category->name;
                    $product_data = null; // Initialize details to null

                    switch ($product->category->name) {
                        case 'clothing':
                            $product_data = [
                                    "id" => $product->id,
                                    "category" => $category,
                                    "name" => $product->name,
                                    "price" => $product->price,
                                    "image" => $product->images[0]->image ?? null,
                                    "colorImages" => $product->images->pluck('image', 'title')->toArray(),
                                    "description" => $product->material,
                                    "sizes" => explode(',', $product->clothingShoesDetail[0]->sizes ?? null),
                                    "colors" => explode(',', $product->clothingShoesDetail[0]->colors ?? null),
                                    "material" => $product->clothingShoesDetail[0]->material ?? null,
                                    "style" => $product->clothingShoesDetail[0]->style ?? null,
                                    "stock" => $product->stock,
                            ];

                            $response[] = $product_data;
                            break;
                        case 'shoes':
                                $product_data = [
                                        "id" => $product->id,
                                        "category" => $category,
                                        "name" => $product->name,
                                        "price" => $product->price,
                                        "image" => $product->images[0]->image ?? null,
                                        "colorImages" => $product->images->pluck('image', 'title')->toArray(),
                                        "description" => $product->material,
                                        "sizes" => explode(',', $product->clothingShoesDetail[0]->sizes ?? null),
                                        "colors" => explode(',', $product->clothingShoesDetail[0]->colors ?? null),
                                        "material" => $product->clothingShoesDetail[0]->material ?? null,
                                        "style" => $product->clothingShoesDetail[0]->style ?? null,
                                        "stock" => $product->stock,
                                ];
    
                                $response[] = $product_data;
                                break;
                        case 'books':
                            $product_data = [
                                "id" => $product->id,
                                "category" => $category,
                                "name" => $product->name,
                                "price" => $product->price,
                                "image" => $product->images[0]->image ?? null,
                                "imageViews" => $product->images->pluck('image', 'title')->toArray(),
                                "description" => $product->material,
                                "author" => $product->booksDetail[0]->author ?? null,
                                "isbn" => $product->booksDetail[0]->isbn ?? null,
                                "genre" => $product->booksDetail[0]->genre ?? null,
                                "publication_date" => $product->booksDetail[0]->publication_date ?? null,
                                "format" => explode(',', $product->booksDetail[0]->format ?? null),
                                "stock" => $product->stock,
                            ];
    
                            $response[] = $product_data;
                            break;
                        case 'real_estate':
                            $product_data = [
                                "id" => $product->id,
                                "category" => $category,
                                "name" => $product->name,
                                "price" => $product->price,
                                "image" => $product->images[0]->image ?? null,
                                "imageViews" => $product->images->pluck('image', 'title')->toArray(),
                                "description" => $product->material,
                                "location" => $product->realEstateDetail[0]->location ?? null,
                                "type" => $product->realEstateDetail[0]->type ?? null,
                                "bedrooms" => $product->realEstateDetail[0]->bedrooms ?? null,
                                "bathrooms" => $product->realEstateDetail[0]->bathrooms ?? null,
                                "square_footage" => $product->realEstateDetail[0]->square_footage ?? null,
                                "contact_email" => $product->realEstateDetail[0]->contact_email ?? null,
                                "contact_phone" => $product->realEstateDetail[0]->contact_phone ?? null,
                                "stock" => $product->stock,
                            ];
    
                            $response[] = $product_data;
                            break;
                        case 'cars':
                            $product_data = [
                                "id" => $product->id,
                                "category" => $category,
                                "name" => $product->name,
                                "price" => $product->price,
                                "image" => $product->images[0]->image ?? null,
                                "imageViews" => $product->images->pluck('image', 'title')->toArray(),
                                "description" => $product->material,
                                "make" => $product->carsDetail[0]->make ?? null,
                                "model" => $product->carsDetail[0]->model ?? null,
                                "year" => $product->carsDetail[0]->year ?? null,
                                "mileage" => $product->carsDetail[0]->mileage ?? null,
                                "fuel_type" => $product->carsDetail[0]->fuel_type ?? null,
                                "contact_email" => $product->carsDetail[0]->contact_email ?? null,
                                "contact_phone" => $product->carsDetail[0]->contact_phone ?? null,
                                "stock" => $product->stock,
                            ];
    
                            $response[] = $product_data;
                            break;
                        default:
                            $product_data = [
                                "id" => $product->id,
                                "category" => $category,
                                "name" => $product->name,
                                "price" => $product->price,
                                "image" => $product->images[0]->image ?? null,
                                "description" => $product->material,
                                "stock" => $product->stock,
                            ];
                            break;
                    }

                }
    
                return response()->json([
                    'success' => true,
                    'message' => 'Products fetched successfully',
                    'data' => $response,
                ], 200);
            
        }
    }



