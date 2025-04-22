<?php

namespace App\Console\Commands;

use App\Models\CronPosting;
use App\Models\Expenditure;
use App\Models\ExpenditureType;
use App\Models\PurchaseDetails;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccrualAccounting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:accrual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        
                    
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Fetch transactions where posting_day is today, end_date's month is <= current month, and status is not posted
        $transactions = Transaction::where('posting_day', now()->day)
                                  ->whereMonth('end_date', '>=', now()->month)
                                  ->get();
    
        if ($transactions->isNotEmpty()) {
            DB::beginTransaction();
            try {
                foreach ($transactions as $transaction) {
                    // Calculate duration (if needed; assuming monthly_value is precomputed)
                    $duration = Carbon::parse($transaction->start_date)->diffInMonths(Carbon::parse($transaction->end_date)) ?: 1;
    
                    // Register ledger entry for sales
                    registerLedger(
                        'sales',
                        'sales_' . $transaction->id,
                        $transaction->monthly_value, // Use monthly_value directly
                        $transaction->shop_id,
                        $transaction->payment_type,
                        $transaction->payment_method,
                        0, // tax
                        0, // discount
                        null, // customer_id
                        null, // reference_id
                        1 // is_active
                    );

                    CronPosting::create([
                        'type' => 'sales',
                        'transaction_id' => $transaction->id,
                        'started_at' => $transaction->start_date,
                        'ended_at' => $transaction->end_date,
                        'status' => 'posted',
                        'amount' => $transaction->monthly_value,
                    ]);
    
                    // Mark transaction as posted
                    // $transaction->update(['status' => 'posted']);
                }
    
                DB::commit();
                Log::info('Processed ' . $transactions->count() . ' transactions for posting_day ' . now()->day);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to process transactions: ' . $e->getMessage());
                throw $e; // Or handle as needed
            }
        } else {
            Log::info('No transactions found for posting_day ' . now()->day);
        }

        // expenditure
        $expenditures = Expenditure::where('posting_day', now()->day)
                        ->whereMonth('end_date', '>=', now()->month)
                        ->get();

            if ($expenditures->isNotEmpty()) {
                            DB::beginTransaction();
                            try {
                                foreach ($expenditures as $expenditure) {
                                    // Calculate duration (if needed; assuming monthly_value is precomputed)
                                    $duration = Carbon::parse($expenditure->start_date)->diffInMonths(Carbon::parse($expenditure->end_date)) ?: 1;
                                    $type = ExpenditureType::find($expenditure->expenditure_type_id);
                    
                                    // Register ledger entry for sales
                                    registerLedger(
                                        'expenditure',
                                        'exp_' . $expenditure->id,
                                        $expenditure->monthly_value, // Use monthly_value directly
                                        $expenditure->shop_id,
                                        $expenditure->payment_type,
                                        $expenditure->payment_method,
                                        0, // tax
                                        0, // discount
                                        null, // customer_id
                                        $type->expenditure_type, // reference_id
                                        1, // is_active
                                         // expenditure type
                                    );
                    
                                    // Mark transaction as posted
                                    // $transaction->update(['status' => 'posted']);
                                    CronPosting::create([
                                        'type' => 'expenditure',
                                        'transaction_id' => $expenditure->id,
                                        'started_at' => $expenditure->start_date,
                                        'ended_at' => $expenditure->end_date,
                                        'status' => 'posted',
                                        'amount' => $expenditure->monthly_value,
                                    ]);
                                }
                    
                                DB::commit();
                                Log::info('Processed ' . $expenditure->count() . ' expenditures for posting_day ' . now()->day);
                            } catch (\Exception $e) {
                                DB::rollBack();
                                Log::error('Failed to process expenditures: ' . $e->getMessage());
                                throw $e; // Or handle as needed
                            }
                        } else {
                            Log::info('No expenditures found for posting_day ' . now()->day);
                        }
    
        // purchase
        $purchase_detail = PurchaseDetails::where('posting_day', now()->day)
        ->whereMonth('end_date', '>=', now()->month)
        ->get();

        if ($purchase_detail->isNotEmpty()) {
            DB::beginTransaction();
            try {
                foreach ($purchase_detail as $purchase_detail_item) {
                    // Calculate duration (if needed; assuming monthly_value is precomputed)
                    $duration = Carbon::parse($purchase_detail_item->start_date)->diffInMonths(Carbon::parse($purchase_detail_item->end_date)) ?: 1;
                    $type = ExpenditureType::find($purchase_detail_item->expenditure_type_id);
    
                    // Register ledger entry for sales
                    registerLedger(
                        'purchase',
                        'purch_' . $purchase_detail_item->id,
                        $purchase_detail_item->monthly_value, // Use monthly_value directly
                        $purchase_detail_item->shop_id,
                        $purchase_detail_item->payment_type,
                        $purchase_detail_item->payment_method,
                        0, // tax
                        0, // discount
                        getCostPrice($purchase_detail_item->product_id, $purchase_detail_item->qty), // customer_id
                        null, // reference_id
                        1, // is_active
                         // expenditure type
                    );
    
                    // Mark transaction as posted
                    // $transaction->update(['status' => 'posted']);
                    CronPosting::create([
                        'type' => 'purchase',
                        'transaction_id' => $purchase_detail_item->id,
                        'started_at' => $purchase_detail_item->start_date,
                        'ended_at' => $purchase_detail_item->end_date,
                        'status' => 'posted',
                        'amount' => $purchase_detail_item->monthly_value,
                    ]);
                }
    
                DB::commit();
                Log::info('Processed ' . $purchase_detail_item->count() . ' expenditures for posting_day ' . now()->day);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to process expenditures: ' . $e->getMessage());
                throw $e; // Or handle as needed
            }
        } else {
            Log::info('No expenditures found for posting_day ' . now()->day);
        }
        return 0;
    }
}
