<?php

namespace App\Http\Controllers;

use App\Models\Expenditure;
use App\Models\ExpenditureType;
use App\Models\PurchaseDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpenditureController extends Controller
{
    public function new_type(Request $request){
        $shopId = request()->query('shop_id');
        $validated = Validator::make($request->all(), ['name' => 'required']);
        if($validated){
        $shopId = request()->query('shop_id');
            $check = applyShopFilter(ExpenditureType::where(strtolower('name'), strtolower($request['name']))->where('expenditure_type', $request['expenditure_type']), $shopId)
                        ->first();

            if(!$check){
                ExpenditureType::create([
                    'name' => $request['name'],
                    'expenditure_type' => $request['expenditure_type'],
                    'user_id' => Auth::user()->id,
                    'useful_life' => $request['useful_life'],
                    'shop_id' => $shopId
                ]);

                return res_completed('created');
            }
            return res_completed('type already exists');
        }
    }

    public function update_type(Request $request, $id){
        $type = ExpenditureType::find($id);
        if($type->exists()){
            $validated = Validator::make($request->all(), ['name' => 'required']);
            if($validated){
                $type->update([
                    'name' => $request['name'],
                    'expenditure_type' => $request['expenditure_type'],
                    'user_id' => Auth::user()->id,
                    'useful_life' => $request['useful_life']
                ]);
                return res_completed('updated');
            }
        }
        return res_not_found('type not found');
    }

    public function all_types(){
        return res_success('all types', ExpenditureType::all());
    }

    public function delete_types($id){
        ExpenditureType::findOrFail($id)->delete();
        return res_completed('deleted');
    }

    public function new_expenditure(Request $request){
        $shopId = request()->query('shop_id');

        $validated = Validator::make($request->all(), [
            'expenditure_type_id' => 'required',
            'amount' => 'required',
        ]);
        
        if($validated){
            Expenditure::create([
                'expenditure_type_id' => $request['expenditure_type_id'],
                'amount' => $request['amount'],
                'qty' => $request['qty'],
                'user_id' => Auth::user()->id,
                'shop_id' => $shopId

            ]);
            return res_completed('created');
        }
    }

    public function update_expenditure(Request $request, $id){
        $find = Expenditure::find($id);
        
        if($find){
            $validated = Validator::make($request->all(), [
                'expenditure_type_id' => 'required',
                'amount' => 'required',
                'qty' => $request['qty'],

            ]);
            if($validated){
                $find->update([
                    'expenditure_type_id' => $request['expenditure_type_id'],
                    'amount' => $request['amount'],
                    'user_id' => Auth::user()->id,
                ]);
                return res_completed('update');
            }
        }
        return res_not_found('not found');
    }

    public function all_expenditures(Request $request){
        $shopId = $request->query('shop_id');

        return res_success('all', applyShopFilter(Expenditure::with('type')->with('user'), $shopId)->get());
    }

    public function delete_expenditure($id){
        Expenditure::findOrFail($id)->delete();
        return res_completed('deleted');
    }

    public function report(Request $request){
        $shopId = $request->query('shop_id');
        $start_date  = $request['start_date'];
        $end_date = $request['end_date'];
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if($validated){
            $report = applyShopFilter(Expenditure::whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])
                                ->with('type')->with('user'), $shopId)->get();
            return res_success('expenditures', $report);
        }
    }

  

    public function updateExpenditurPaymentPlan(Request $request, $id){
        $expenditure = Expenditure::find($id);
        $shopId = request()->query('shop_id');
        if ($expenditure) {
            $expenditure->update([
                "payment_method" => $request["payment_method"],
                "payment_status" => $request["payment_status"],
                "part_payment_amount" => $request["part_payment_amount"],
                "duration" => $request["duration"]
            ]);

            if ($request["payment_status"] == 'paid') {
                bankService(
                    $expenditure['amount'], 
                    "EXPENDITURE - PAID",
                    $expenditure->id,
                    $shopId,
                    "DEBIT"
                );
            }else if ($request['payment_method'] == 'part_payment') {
                bankService(
                    $request['part_payment_amount'], 
                    "EXPENDITURE - PART PAYMENT",
                    $expenditure->id,
                    $shopId,
                    "DEBIT"
                );
            }else{
                bankService(
                    $expenditure['amount'], 
                    "EXPENDITURE - CREDIT",
                    $expenditure->id,
                    $shopId,
                    "DEBIT"
                );
            }
        }
        
        return res_completed('updated');

    }
}
