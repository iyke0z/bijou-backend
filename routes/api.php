<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenditureController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\ExpenditureType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\TextUI\XmlConfiguration\Group;
use PHPUnit\TextUI\XmlConfiguration\Groups;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function (){
        // Auth
    Route::post('sell/', [TransactionController::class, 'sell']);
    Route::post('sell/update', [TransactionController::class, 'update_sale']);
    Route::post('sell/orders', [TransactionController::class, "get_active_orders"]);
    Route::post('sell/pay', [TransactionController::class, "pay"]);
    Route::post('sell/delete', [TransactionController::class, 'delete_sale']);
    Route::get('food-orders', [TransactionController::class, "food_prep_status"]);
    Route::get('drink-orders', [TransactionController::class, "drinks_prep_status"]);
    Route::post('update-prep-status', [TransactionController::class, "update_prep_status"]);
    Route::get('product/', [ProductController::class, 'all_products']);
    Route::get('customer/all', [CustomerController::class, 'all_customers']);
    Route::get('banks/', [AuthController::class, 'all_banks']);

    Route::post('login', [AuthController::class, 'login']);
    Route::get('/business/details', [AuthController::class, 'show_business']);
    Route::post('/business/create', [AuthController::class, 'create_business_details']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', function (Request $request) {
            $user = User::with('role')
            ->with('purchase')
            ->with('sales')
            ->with('access_log')
            ->with('expenditure_types')
            ->with('expenditure')->find($request->user()->id);
            return $user;
        });
        Route::post('logout', [AuthController::class, 'logout']);
        Route::prefix('user')->group(function (){
            Route::post('create', [UserController::class, 'create_user']);
            Route::get('{id}', [UserController::class, 'get_user']);
            Route::post('update/{id}', [UserController::class, 'update_user']);
            Route::post('assign/{id}', [UserController::class, 'assign_user_priviledge']);
            Route::post('delete/{id}', [UserController::class,  'delete_user']);
            Route::get('/', [UserController::class,  'all_users']);
        });
        Route::get('roles',[UserController::class,'all_roles']);
        Route::get('priviledges',[UserController::class,'all_priviledges']);
        Route::post('role/create',[UserController::class,'create_role']);
        Route::post('role/delete/{id}',[UserController::class,'delete_role']);
        Route::post('priviledge/create',[UserController::class,'create_priviledge']);
        Route::post('priviledge/delete/{id}',[UserController::class,'delete_priviledge']);
        Route::post('assign/role/{id}', [UserController::class, 'assign_role_priviledge']);
        Route::prefix('category')->group(function (){
            Route::post('/create', [ProductController::class, 'create_category']);
            Route::post('/update/{id}', [ProductController::class, 'update_category']);
            Route::post('/delete/{id}', [ProductController::class, 'delete_category']);
            Route::get('/', [ProductController::class, 'all_categories']);
        });
        Route::prefix('product')->group(function (){
            Route::post('/create', [ProductController::class, 'create_product']);
            Route::patch('/update/{id}', [ProductController::class, 'update_product']);
            Route::post('/delete/{id}', [ProductController::class, 'delete_product']);
            Route::post('/report/{id}', [ProductController::class, 'generate_product_report']);
            Route::post('/report/all/{id}', [ProductController::class, 'general_generate_product_report']);
            Route::post('/upload/image', [ProductController::class, 'upload_images']);
        });
        Route::prefix('purchase')->group(function (){
            Route::post('/create', [ProductController::class, 'new_purchase']);
            Route::post('/update/{id}', [ProductController::class, 'update_purchase']);
            Route::post('/detail/delete/{id}', [ProductController::class, 'delete_purchase_detail']);
            Route::post('/delete/{id}', [ProductController::class, 'delete_purchase']);
            Route::get('/', [ProductController::class, 'all_purchases']);
            Route::post('/report', [ProductController::class, 'purchase_report']);
        });
        Route::prefix('customer')->group(function (){
            Route::post('/create', [CustomerController::class, 'create_customer']);
            Route::post('/update/{id}', [CustomerController::class, 'update_customer']);
            Route::post('/fund/{id}', [CustomerController::class, 'fund_customer']);
            Route::post('/delete/{id}', [CustomerController::class, 'delete_customer']);
            Route::post('/details/{id}', [CustomerController::class, 'customer_details']);
        });
        Route::prefix('discount')->group(function (){
            Route::post('/create', [TransactionController::class, 'create_discount']);
            Route::post('/update/{id}', [TransactionController::class, 'update_discount']);
            Route::post('/delete/{id}', [TransactionController::class, 'delete_discount']);
            Route::post('/customer/{id}' , [TransactionController::class, 'customer_discount']);
            Route::get('/', [TransactionController::class, 'all_discounts']);
            Route::get('/available', [TransactionController::class, 'search_discount']);
            Route::post('/customer/delete/{id}/{discount}', [TransactionController::class, 'delete_customer_discount']);
        });
        Route::prefix('sell')->group(function (){
            Route::get('/all', [TransactionController::class, 'all_sales']);
            Route::post('/periodic', [TransactionController::class, 'periodic_sales']);
            Route::post('/today', [TransactionController::class, 'sales_report_today']);
            Route::patch('/verify/pod', [TransactionController::class, 'payondelivery']);
            Route::patch('/verify/poc', [TransactionController::class, 'payoncredit']);
        });
        Route::prefix('business')->group(function (){
            Route::post('/update/{id}', [AuthController::class, 'update_business_details']);
            Route::post('/delete/{id}', [AuthController::class, 'delete_business_details']);
            Route::post('/expire', [AuthController::class, 'expire']);
            Route::post('/restore', [AuthController::class, 'restore']);
        });
        Route::prefix('activation')->group(function (){
            Route::post('/new', [AuthController::class, 'new_code']);
            Route::post('/activate', [AuthController::class, 'use_code']);
        });
        Route::prefix('expenditure')->group(function (){
            Route::post('/type/create', [ExpenditureController::class, 'new_type']);
            Route::post('/type/update/{id}', [ExpenditureController::class, 'update_type']);
            Route::post('/type/delete/{id}', [ExpenditureController::class, 'delete_types']);
            Route::get('/type', [ExpenditureController::class, 'all_types']);
            Route::post('/create', [ExpenditureController::class, 'new_expenditure']);
            Route::post('/update/{id}', [ExpenditureController::class, 'update_expenditure']);
            Route::post('/delete/{id}', [ExpenditureController::class, 'delete_expenditure']);
            Route::get('/', [ExpenditureController::class, 'all_expenditures']);
            Route::post('/report', [ExpenditureController::class, 'report']);
        });
        Route::prefix('report')->group(function (){
            Route::post('/', [ReportController::class, 'general_report']);
            Route::post('/deleted', [ReportController::class, 'cancelled_receipt']);
        });
        Route::prefix('banks')->group(function (){
            Route::post('/store', [AuthController::class, 'create_bank']);
            Route::post('/update', [AuthController::class, 'update_bank']);
            Route::post('/delete', [AuthController::class, 'delete_bank']);
        });

        Route::post('generate-code', [AuthController::class, 'generate_user_codes']);




















    });
});



