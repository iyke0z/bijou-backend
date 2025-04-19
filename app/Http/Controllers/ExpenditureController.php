<?php

namespace App\Http\Controllers;

use App\Models\Expenditure;
use App\Models\ExpenditureSupportingDocuments;
use App\Models\ExpenditureType;
use App\Models\Liquidity;
use App\Models\ExpenditureDetails;
use App\Models\ExpenditureSupportingDocument;
use App\Models\LogisticsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

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
            $new_expenditure = Expenditure::create([
                'expenditure_type_id' => $request['expenditure_type_id'],
                'amount' => $request['amount'],
                'qty' => $request['qty'],
                'user_id' => Auth::user()->id,
                'shop_id' => $shopId
            ]);
            $expenditure = ExpenditureType::findOrFail($request['expenditure_type_id']);



            // if expenditure is logistics deduct from logistics account
            if($expenditure->expenditure_type == "logistics"){
                $previous_balance = LogisticsAccount::get()->last()->current_balance ?? 0;
                $current_balance = $previous_balance - intval($request['amount']);
                LogisticsAccount::create([
                    "transaction_id" => $expenditure->id,
                    "amount" => $request['amount'],
                    "type" => 'debit',
                    'shop_id' => $shopId,
                    "previous_balance" => $previous_balance ?? 0,
                    "current_balance" => $current_balance
                ]);
            }else{
                bankService(
                    $request['amount'], 
                    "EXPENDITURE - PAID",
                    $new_expenditure->id,
                    $shopId,
                    "DEBIT"
                );
            }

            // if expenditure is logistics
            return res_completed('created');
        }
    }

    public function update_expenditure(Request $request, $id){
        $find = Expenditure::find($id);
        $shopId = request()->query('shop_id');

        
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

                //update bank balance
                $bankBalance = Liquidity::where('transaction_reference', $id)->first();
                $bankBalance->transaction_amount =  $request['amount'];
                $bankBalance->save();
                
                
                return res_completed('update');
            }
        }
        return res_not_found('not found');
    }

    public function all_expenditures(Request $request){
        $shopId = $request->query('shop_id');

        return res_success('all', applyShopFilter(Expenditure::with('type')->with('documents')->with('user'), $shopId)->get());
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
        $request["payment_status"] = "not_paid";
        $type = ExpenditureType::find($expenditure->expenditure_type_id);

        if($request["payment_method"] == 'part_payment') {
            $request["payment_status"] = "not_paid";
        }else if($request["payment_method"] == 'on_credit') {
            $request["payment_status"] = "not_paid";
        }else{
            $request["payment_status"] = "paid";
        }

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
        registerLedger(
            'purchases', 
            $type->expenditure_type,
            $expenditure['amount'],
            $expenditure->id, 
            $shopId, 
            0, 
            $request['payment_method'], 
            $request['part_payment_amount'] ?? 0);
        return res_completed('updated');

    }
    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string',
            'expenditure_id' => 'required|integer|exists:expenditures,id',
            'files.*' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
        ]);
        

        $documents = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('expenditure_documents');
        
            $documents[] = ExpenditureSupportingDocument::create([
                'document_type' => $request->document_type,
                'path' => $path,
                'expenditure_id' => $request->expenditure_id,
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
            'id' => 'required|integer|exists:expenditure_supporting_documents,id',
        ]);

        $document = ExpenditureSupportingDocument::findOrFail($request->id);

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
        $documents = ExpenditureSupportingDocument::where('Expenditure_id', $id)->get();
    
        if ($documents->isEmpty()) {
            return response()->json(['error' => 'No documents found.'], 404);
        }
    
        // Create a temporary ZIP file
        $zipFileName = 'expenditure_documents_' . $id . '.zip';
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
