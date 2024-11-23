<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenditureController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebHookController;
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
Route::post('/webhook', [WebHookController::class, 'webHookHandler']);

Route::prefix('v1')->group(function (){
    Route::prefix('admin')->group(function () {
        Route::get('packages/', [SuperAdminController::class, 'getPackages']);
        Route::post('create-package', [SuperAdminController::class, 'createPackage']);
        Route::put('update-package/{id}', [SuperAdminController::class, 'updatePackage']);
        Route::delete('delete-package/', [SuperAdminController::class, 'deletePackage']);
    });
        // Auth
    Route::post('sell/', [TransactionController::class, 'sell'])->middleware('IaActive');
    Route::post('sell/update', [TransactionController::class, 'update_sale'])->middleware('IaActive');
    Route::post('sell/orders', [TransactionController::class, "get_active_orders"])->middleware('IaActive');
    Route::post('sell/pay', [TransactionController::class, "pay"])->middleware('IaActive');
    Route::post('sell/delete', [TransactionController::class, 'delete_sale'])->middleware('IaActive');
    Route::get('food-orders', [TransactionController::class, "food_prep_status"]);
    Route::get('drink-orders', [TransactionController::class, "drinks_prep_status"]);
    Route::post('update-prep-status', [TransactionController::class, "update_prep_status"])->middleware('IaActive');
    Route::get('product/', [ProductController::class, 'all_products']);
    Route::get('customer/all', [CustomerController::class, 'all_customers']);
    Route::get('banks/', [AuthController::class, 'all_banks']);
    Route::get('get-expiration', [AuthController::class, 'get_expiration']);
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::get('/business/details', [AuthController::class, 'show_business']);
    Route::post('/business/create', [AuthController::class, 'create_business_details'])->middleware('IaActive');
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (Request $request) {
            $user = User::with('role')
            // ->with('purchase')
            // ->with('sales')
            ->with('access_log')
            ->with('access_code')
            // ->with('expenditure_types')
            // ->with('expenditure')
            ->find($request->user()->id);
            return $user;
        });
        Route::post('logout', [AuthController::class, 'logout']);
        Route::prefix('user')->group(function (){
            Route::post('create', [UserController::class, 'create_user'])->middleware('IaActive')->middleware('IaActive');
            Route::get('{id}', [UserController::class, 'get_user'])->middleware('IaActive');
            Route::post('update/{id}', [UserController::class, 'update_user'])->middleware('IaActive')->middleware('IaActive');
            Route::post('assign/{id}', [UserController::class, 'assign_user_priviledge'])->middleware('IaActive')->middleware('IaActive');
            Route::post('delete/{id}', [UserController::class,  'delete_user'])->middleware('IaActive')->middleware('IaActive');
            Route::get('/', [UserController::class,  'all_users']);
        });
        Route::get('roles',[UserController::class,'all_roles'])->middleware('IaActive');
        Route::get('priviledges',[UserController::class,'all_priviledges'])->middleware('IaActive');
        Route::post('role/create',[UserController::class,'create_role'])->middleware('IaActive')->middleware('IaActive');
        Route::post('role/delete/{id}',[UserController::class,'delete_role'])->middleware('IaActive')->middleware('IaActive');
        Route::post('priviledge/create',[UserController::class,'create_priviledge'])->middleware('IaActive')->middleware('IaActive');
        Route::post('priviledge/delete/{id}',[UserController::class,'delete_priviledge'])->middleware('IaActive')->middleware('IaActive');
        Route::post('assign/role/{id}', [UserController::class, 'assign_role_priviledge'])->middleware('IaActive')->middleware('IaActive');
        Route::prefix('category')->group(function (){
            Route::post('/create', [ProductController::class, 'create_category'])->middleware('IaActive');
            Route::post('/update/{id}', [ProductController::class, 'update_category'])->middleware('IaActive');
            Route::post('/delete/{id}', [ProductController::class, 'delete_category'])->middleware('IaActive');
            Route::get('/', [ProductController::class, 'all_categories']);
        });
        Route::prefix('product')->group(function (){
            Route::post('/create', [ProductController::class, 'create_product'])->middleware('IaActive');
            Route::patch('/update/{id}', [ProductController::class, 'update_product'])->middleware('IaActive');
            Route::post('/delete/{id}', [ProductController::class, 'delete_product'])->middleware('IaActive');
            Route::post('/report/{id}', [ProductController::class, 'generate_product_report'])->middleware('IaActive');
            Route::post('/report/all/{id}', [ProductController::class, 'general_generate_product_report'])->middleware('IaActive');
            Route::post('/upload/image', [ProductController::class, 'upload_images'])->middleware('IaActive');
        });
        Route::prefix('purchase')->group(function (){
            Route::post('/create', [ProductController::class, 'new_purchase'])->middleware('IaActive');
            Route::post('/update/{id}', [ProductController::class, 'update_purchase'])->middleware('IaActive');
            Route::post('/detail/delete/{id}', [ProductController::class, 'delete_purchase_detail'])->middleware('IaActive');
            Route::post('/delete/{id}', [ProductController::class, 'delete_purchase'])->middleware('IaActive');
            Route::get('/', [ProductController::class, 'all_purchases'])->middleware('IaActive');
            Route::post('/report', [ProductController::class, 'purchase_report'])->middleware('IaActive');
        });
        Route::prefix('customer')->group(function (){
            Route::post('/create', [CustomerController::class, 'create_customer'])->middleware('IaActive');
            Route::post('/update/{id}', [CustomerController::class, 'update_customer'])->middleware('IaActive');
            Route::post('/fund/{id}', [CustomerController::class, 'fund_customer'])->middleware('IaActive');
            Route::post('/delete/{id}', [CustomerController::class, 'delete_customer'])->middleware('IaActive');
            Route::post('/details/{id}', [CustomerController::class, 'customer_details'])->middleware('IaActive');
        });
        Route::prefix('discount')->group(function (){
            Route::post('/create', [TransactionController::class, 'create_discount'])->middleware('IaActive');
            Route::post('/update/{id}', [TransactionController::class, 'update_discount'])->middleware('IaActive');
            Route::post('/delete/{id}', [TransactionController::class, 'delete_discount'])->middleware('IaActive');
            Route::post('/customer/{id}' , [TransactionController::class, 'customer_discount'])->middleware('IaActive');
            Route::get('/', [TransactionController::class, 'all_discounts'])->middleware('IaActive');
            Route::get('/available', [TransactionController::class, 'search_discount'])->middleware('IaActive');
            Route::post('/customer/delete/{id}/{discount}', [TransactionController::class, 'delete_customer_discount'])->middleware('IaActive');
        });
        Route::prefix('sell')->group(function (){
            Route::get('/all', [TransactionController::class, 'all_sales']);
            Route::post('/periodic', [TransactionController::class, 'periodic_sales'])->middleware('IaActive');
            Route::post('/today', [TransactionController::class, 'sales_report_today'])->middleware('IaActive');
            Route::patch('/verify/pod', [TransactionController::class, 'payondelivery'])->middleware('IaActive');
            Route::patch('/verify/poc', [TransactionController::class, 'payoncredit'])->middleware('IaActive');
        });
        Route::prefix('business')->group(function (){
            Route::post('/update/{id}', [AuthController::class, 'update_business_details'])->middleware('IaActive');
            Route::post('/delete/{id}', [AuthController::class, 'delete_business_details'])->middleware('IaActive');
            Route::post('/expire', [AuthController::class, 'expire'])->middleware('IaActive');
            Route::post('/restore', [AuthController::class, 'restore'])->middleware('IaActive');
        });
        Route::prefix('activation')->group(function (){
            Route::post('/new', [AuthController::class, 'new_code'])->middleware('IaActive');
            Route::post('/activate', [AuthController::class, 'use_code'])->middleware('IaActive');
        });
        Route::prefix('expenditure')->group(function (){
            Route::post('/type/create', [ExpenditureController::class, 'new_type'])->middleware('IaActive');
            Route::post('/type/update/{id}', [ExpenditureController::class, 'update_type'])->middleware('IaActive');
            Route::post('/type/delete/{id}', [ExpenditureController::class, 'delete_types'])->middleware('IaActive');
            Route::get('/type', [ExpenditureController::class, 'all_types']);
            Route::post('/create', [ExpenditureController::class, 'new_expenditure'])->middleware('IaActive');
            Route::post('/update/{id}', [ExpenditureController::class, 'update_expenditure'])->middleware('IaActive');
            Route::post('/delete/{id}', [ExpenditureController::class, 'delete_expenditure'])->middleware('IaActive');
            Route::get('/', [ExpenditureController::class, 'all_expenditures']);
            Route::post('/report', [ExpenditureController::class, 'report'])->middleware('IaActive');
        });
        Route::prefix('report')->group(function (){
            Route::post('/', [ReportController::class, 'general_report'])->middleware('IaActive');
            Route::post('/deleted', [ReportController::class, 'cancelled_receipt'])->middleware('IaActive');
            Route::post('/sales-performance', [ReportController::class, 'getSalesPerformance'])->middleware('IaActive');
            Route::post('/opex-performance', [ReportController::class, 'getOpexPerformance'])->middleware('IaActive');
            Route::get('/debt-performance', [ReportController::class, 'getCustomerInsightPerformance'])->middleware('IaActive');
            Route::post('/cogs-performance', [ReportController::class, 'getCogs'])->middleware('IaActive');
            Route::post('/method-performance', [ReportController::class, 'getPaymentMethodPerformance'])->middleware('IaActive');
            Route::post('/profit-loss', [ReportController::class, 'getProfitLoss'])->middleware('IaActive');
            
        });

        Route::prefix('banks')->group(function (){
            Route::post('/store', [AuthController::class, 'create_bank'])->middleware('IaActive');
            Route::post('/update', [AuthController::class, 'update_bank'])->middleware('IaActive');
            Route::post('/delete', [AuthController::class, 'delete_bank'])->middleware('IaActive');
        });

        Route::post('generate-code', [AuthController::class, 'generate_user_codes'])->middleware('IaActive');
        Route::post('transaction-report', [ReportController::class, 'generate_report'])->middleware('IaActive');
        Route::post('user-sales-report', [ReportController::class, 'generate_sales_report'])->middleware('IaActive');
    });
});



