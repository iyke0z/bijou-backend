<?php

namespace App\Console\Commands;

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
                                  ->whereMonth('end_date', '<=', now()->month)
                                  ->where('status', '!=', 'posted')
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
    
        return 0;
    }
}
