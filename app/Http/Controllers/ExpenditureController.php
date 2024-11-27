<?php

namespace App\Http\Controllers;

use App\Models\Expenditure;
use App\Models\ExpenditureType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpenditureController extends Controller
{
    public function new_type(Request $request){
        $validated = Validator::make($request->all(), ['name' => 'required']);
        if($validated){
            $check = ExpenditureType::where(strtolower('name'), strtolower($request['name']))->first();
            if(!$check){
                ExpenditureType::create([
                    'name' => $request['name'],
                    'expenditure_type' => $request['expenditure_type'],
                    'user_id' => Auth::user()->id,
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
        $validated = Validator::make($request->all(), [
            'expenditure_type_id' => 'required',
            'amount' => 'required',
        ]);
        if($validated){
            Expenditure::create([
                'expenditure_type_id' => $request['expenditure_type_id'],
                'amount' => $request['amount'],
                'user_id' => Auth::user()->id,
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

    public function all_expenditures(){
        return res_success('all', Expenditure::with('type')->with('user')->get());
    }

    public function delete_expenditure($id){
        Expenditure::findOrFail($id)->delete();
        return res_completed('deleted');
    }

    public function report(Request $request){
        $start_date  = $request['start_date'];
        $end_date = $request['end_date'];
        $validated = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if($validated){
            $report = Expenditure::whereBetween(DB::raw('date(created_at)'), [$start_date, $end_date])
                                ->with('type')->with('user')
                                 ->get();
            return res_success('expenditures', $report);
        }
    }
}
