<?php

namespace App\Http\Controllers;

use App\Models\Expenditure;
use App\Models\PurchaseDetails;
use App\Models\Sales;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function general_report(Request $request){
        $start_date  = $request['start_date']; //start at 6:00am
        $end_date = $request['end_date']; //start at 4:am
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'platform' => 'required'
        ]);

        if($validated){
            if($request['platform'] != 'all'){
                $getTransactions = Transaction::whereBetween(\DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                    ->where('type', "!=", 'credit')
                                    ->where('type', "!=", 'new_acount')
                                    ->where('type', "!=", 'cancelled')
                                    ->where('platform', $request['platform'])
                                    ->with('customer')
                                    ->with(['sales' => function($q){
                                        $q->join('products', 'sales.product_id', 'products.id');
                                    }])->get();
                $get_sales = Sales::join('transactions', 'sales.ref', 'transactions.id')
                ->whereBetween(\DB::raw('DATE(transactions.`created_at`)'), [$start_date, $end_date])
                ->where('transactions.status', '!=', 'cancelled')
                ->where('transactions.platform', $request['platform'])
                ->with('product')->with('user')->get();

            }else{
                $getTransactions = Transaction::whereBetween(\DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                    ->where('type', "!=", 'credit')
                                    ->where('type', "!=", 'new_acount')
                                    ->where('type', "!=", 'cancelled')
                                    ->with('customer')
                                    ->with(['sales' => function($q){
                                        $q->join('products', 'sales.product_id', 'products.id');
                                    }])->get();
                $get_sales = Sales::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
                                    ->with('product')->with('user')->get();

            }
                $get_purchases = PurchaseDetails::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
                ->with('purchase')->with('product')->get();

                $get_expenditures = Expenditure::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
                ->with('type')->with('user')->get();

            $report = [
                'transaction' => $getTransactions,
                'sales' => $get_sales,
                'purchases' => $get_purchases,
                'expenditures' => $get_expenditures
            ];

            return res_success('report', $report);

        }

    }

    public function cancelled_receipt(Request $request){
        $start_date  = $request['start_date'];
        $end_date = $request['end_date'];
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if($validated){
            $getTransactions = Transaction::whereBetween(\DB::raw('DATE(`created_at`)'),  [$start_date, $end_date])
                                ->where('type', 'cancelled')
                                ->with('customer')
                                ->with(['sales' => function($q){
                                    $q->join('products', 'sales.product_id', 'products.id');
                                }])->get();

            $get_sales = Sales::whereBetween(\DB::raw('DATE(`created_at`)'), [$start_date, $end_date])
            ->where('type', 'cancelled')
            ->with('product')
            ->with('user')->get();
            $report = [
                'transaction' => $getTransactions,
                'sales' => $get_sales,
            ];

            return res_success('report', $report);

        }

    }

    public function generate_report(){
        // get sales between 6am the previous day - 4am today
        // POS payments should be split based on bank
        // Cash payments
        // Transfer payments
        // customer payments/credit
    }

}
