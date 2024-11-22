<?php

namespace App\Http\Controllers;

use App\Models\BusinessDetails;
use App\Models\Package;
use App\Models\SubscriptionLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as FacadesLog;

class WebHookController extends Controller
{
        public function verifyTransaction($reference){
            $secret = config('services.webhooks.paystack.secret');
            $response = Http::retry(3, 100)->asForm()->withHeaders([
                'Authorization' => "Bearer $secret",
            ])->get('https://api.paystack.co/transaction/verify/'.$reference);
    
            return $response->json();
        }
        
         
        public function webHookHandlerPaystack(Request $request){       
            FacadesLog::info($request);
            // get server origin information
            $secret = config('services.webhooks.paystack.secret');
            $header = $request->header();
            $input = @file_get_contents("php://input");
            $user = BusinessDetails::where('email', $request['data']['customer']['email'])->first();
            
            if($header['x-paystack-signature'][0] !== hash_hmac('sha512', $input, $secret)){
                abort(401);
            }else{
                $verify = $this->verifyTransaction($request['data']['reference']);
                if($verify['data']['status'] == 'success' && $request['event'] == "charge.success"){
                    $user = BusinessDetails::where('email', $request['data']['customer']['email'])->first();
                    $details = [
                        "userid" => $user->id,
                        'ref' => $request['data']['reference']
                    ];
                    // update wallet
                    $user->update(['active' => $user->wallet + $request['data']['amount']/100]);
                    
                    $package = Package::where('price', $request['data']['amount']/100)->first();
                    SubscriptionLog::create([
                        "package_id" => $package->id,
                        "business_id" => $user->id,
                    ]);
                    return response()->json([],200);
                }else{
                    return response()->json([],400);
                }
            }
        }
}
