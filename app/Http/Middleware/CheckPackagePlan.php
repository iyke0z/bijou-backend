<?php

namespace App\Http\Middleware;

use App\Models\Purchase;
use App\Models\SubscriptionLog;
use Closure;
use Illuminate\Http\Request;

class CheckPackagePlan
{
    public function handle(Request $request, Closure $next)
    {
        // Get the route name or path to specifically check for create actions
        $routeName = $request->route()->getName();
        
        // Retrieve the package_id from query parameters
        $packageId = SubscriptionLog::latest()->first()->package_id ?? 1; // Default to 1 if no package_id is found

        // Define the package limits for both monthly and annual packages
        $packageLimits = [
            1 => [  // Free Trial
                'users' => 2,
                'shops' => 2,
                'roles' => 5,
                'categories' => 5,
                'products' => 50,
                'transactions' => 50,
                'expenditures' => 100,
                'customers' => 2,
            ],
            2 => [  // Startup Lite monthly
                'users' => 5,
                'shops' => 2,
                'roles' => 5,
                'categories' => 5,
                'products' => 150,
                'transactions' => 500,
                'expenditures' => 300,
                'customers' => 10,
            ],
            3 => [  // Startup Lite annual
                'users' => 5,
                'shops' => 2,
                'roles' => 5,
                'categories' => 5,
                'products' => 150,
                'transactions' => 500  * 2,
                'expenditures' => 300  * 2,
                'customers' => 10  * 2,
            ],
            4 => [  // Standard Monthly
                'users' => 5,
                'shops' => 5,
                'roles' => 5,
                'categories' => 5,
                'products' => 300,
                'transactions' => 2500,
                'expenditures' => 600,
                'customers' => 50,
            ],
            5 => [  // Standard Anual
                'users' => 5,
                'shops' => 5,
                'roles' => 5,
                'categories' => 5,
                'products' => 300,
                'transactions' => 2500 * 2,
                'expenditures' => 600 * 2,
                'customers' => 50 * 2,
            ],
            6 => [  // Professional
                'users' => 10,
                'shops' => 5,
                'roles' => 5,
                'categories' => 15,
                'products' => 500,
                'transactions' => 5000,
                'expenditures' => 1000,
                'customers' => 600,
            ],
            7 => [  // Professional
                'users' => 10,
                'shops' => 5,
                'roles' => 5,
                'categories' => 15,
                'products' => 500,
                'transactions' => 5000 * 2,
                'expenditures' => 1000 * 2,
                'customers' => 600 * 2,
            ],
            8 => [  // Enterprise
                'users' => PHP_INT_MAX,
                'shops' => PHP_INT_MAX,
                'roles' => PHP_INT_MAX,
                'categories' => PHP_INT_MAX,
                'products' => PHP_INT_MAX,
                'transactions' => PHP_INT_MAX,
                'expenditures' => PHP_INT_MAX,
                'customers' => PHP_INT_MAX,
            ],
            9 => [  // Enterprise
                'users' => PHP_INT_MAX,
                'shops' => PHP_INT_MAX,
                'roles' => PHP_INT_MAX,
                'categories' => PHP_INT_MAX,
                'products' => PHP_INT_MAX,
                'transactions' => PHP_INT_MAX,
                'expenditures' => PHP_INT_MAX,
                'customers' => PHP_INT_MAX,
            ],
        ];
        

        $limits = $packageLimits[$packageId] ??  $packageLimits[1];
        // Get the current usage for specific entities based on the action being performed
        $currentUsage = [];

        // Check if the action corresponds to creating a resource
        if (in_array($routeName, ['create_user', 'create_branches', 'create_roles', 'create_categories', 'create_products', 'create_transactions', 'create_expenditures', 'create_customers'])) {
            $currentUsage = [
                'users' => \App\Models\User::count(),
                'shops' => \App\Models\Shop::count(),
                'roles' => \App\Models\Role::count(),
                'categories' => \App\Models\Category::count(),
                'products' => \App\Models\Product::count(),
                'transactions' => \App\Models\Transaction::count(),
                'expenditures' => \App\Models\Expenditure::count() + Purchase::count(),
                'customers' => \App\Models\Customer::count(),
            ];
        }

        // Map the routes to their respective entities
        $resourceMap = [
            'create_user' => 'users',
            'create_branches' => 'shops',
            'create_roles' => 'roles',
            'create_categories' => 'categories',
            'create_products' => 'products',
            'create_transactions' => 'transactions',
            'create_expenditures' => 'expenditures',
            'create_customers' => 'customers',
        ];

        // Check the specific limit for the resource being created
        if (isset($resourceMap[$routeName])) {
            $resource = $resourceMap[$routeName];

            // Check if the current usage exceeds the limit for the resource
            if ($currentUsage[$resource] >= $limits[$resource]) {
                return response()->json([
                    'message' => "Package plan exceeded for $resource. Allowed: {$limits[$resource]}, Used: {$currentUsage[$resource]}",
                ], 403);
            }
        }

        // Allow the request to proceed if limits are not exceeded
        return $next($request);
    }
}
