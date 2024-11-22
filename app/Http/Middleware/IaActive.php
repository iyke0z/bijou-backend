<?php

namespace App\Http\Middleware;

use App\Models\BusinessDetails;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class IaActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

    public function handle($request, Closure $next)
    {

       
        // Fetch active status details
        $active_status = BusinessDetails::first();

        if (!$active_status || !isset($active_status->expiry_date)) {
            // If no active status or expiry date is not set, take action (e.g., deny access)
            return response()->json(['error' => 'Inactive Subscription'], 403);
        }

        // Get current timestamp
        $current_time = Carbon::now()->timestamp;

        // Compare expiry_date with current time
        if ($active_status->expiry_date < $current_time) {
            // The active status has expired
            return response()->json(['error' => 'Inactive Subscription'], 403);
        }

        if ($active_status->expiry_date === $current_time) {
            // The active status expiry date matches the current time
            // Optionally handle this specific case
        }

        // Allow request to proceed
        return $next($request);
    }

}
