<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBusinessDetailsRequest;
use App\Http\Requests\GenerateCodeRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateBusinessDetailsRequest;
use App\Interfaces\AuthRepositoryInterface;
use App\Models\ActivationCode;
use App\Models\ActivationCodeLog;
use App\Models\Banks;
use App\Models\BusinessDetails;
use App\Traits\AuthTrait;
use App\Traits\BusinessTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use BusinessTrait, AuthTrait;
    public function __construct(AuthRepositoryInterface $authRepo)
    {
        $this->authRepo = $authRepo;
    }
    public function login(LoginRequest $request){
        $validated= $request->validated();
        return $this->authRepo->login($validated);
    }

    public function logout(){
        return $this->authRepo->logout();
    }

    public function create_business_details(CreateBusinessDetailsRequest $request){
        $validated = $request->validated();
        return $this->authRepo->create_business_details($validated);
    }

    public function update_business_details(UpdateBusinessDetailsRequest $request, $id){
        $validated = $request->validated();
        return $this->authRepo->update_business_details($validated, $id);
    }
    public function delete_business_details($id){
        return $this->authRepo->delete_business_details($id);
    }
    public  function show_business(){
        return BusinessTrait::get_details();
    }
    public function expire(){
        return BusinessTrait::expire();
    }

    public function restore(){
        return BusinessTrait::restore();
    }

    public function new_code(Request $request){
        $validated = Validator::make($request->all(), ['code'=>'required']);
        if($validated){
            ActivationCode::create([
                "code"=> AuthTrait::hashString($request['code']),
            ]);
            return res_completed('activation code added');
        }else{
            return res_completed('code is required');
        }
    }

    public function use_code(Request $request){
        $validated = Validator::make($request->all(), ['code' => 'required']);
        if($validated){
            $code = ActivationCode::all();
            if($code){
                foreach ($code as $key) {
                    if(AuthTrait::unHash($key->code) == $request['code']){
                        // update business
                        $business = BusinessDetails::first();
                        $business->expiry_date = strtotime( "+1 month", time() );
                        $business->save();
                        // log_activation
                        ActivationCodeLog::create([
                            'activation_code_id' => $key->id,
                            'business_detail_id'=> $business->id
                        ]);
                        $key->delete();
                        return res_completed('activation successful');
                    }
                    return res_completed('activation code not usable');
                }
            }
            return res_completed('empty');
        }
    }

    public function create_bank(Request $request){
        Banks::create([
            'name' => $request['name']
        ]);

        return res_completed('bank created');
    }

    public function update_bank(Request $request){
        $findBank = Banks::find($request['id']);

        if($findBank->exist()){
            // update bank
            $findBank->update([
                'name' => $request['name']
            ]);

            return res_completed('bank updated');
        }

        return res_not_found("bankd detail does not exist");
    }

    public function delete_bank(Request $request){
        Banks::find($request['id'])->delete();

        return res_completed('deletd');
    }

    public function all_banks(){
        $banks = Banks::all();

        return res_success('banks', $banks);
    }

    public function generate_user_codes(GenerateCodeRequest $request){
        $validated = $request->validated();

        return $this->authRepo->generate_user_codes($validated);
    }
}
