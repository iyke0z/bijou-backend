<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPackagePlan
{
    public function handle(Request $request, Closure $next)
    {
        // Get the route name or path to specifically check for create actions
        $routeName = $request->route()->getName();
        
        // Retrieve the package_id from query parameters
        $packageId = $request->query('package_id');

        // Define the package limits for both monthly and annual packages
        $packageLimits = [
            1 => [  // Free Trial (7 days)
                'users' => 2,
                'branches' => 1,
                'roles' => 3,
                'categories' => 2,
                'products' => 10,
                'transactions' => 50,
                'expenditures' => 100,
                'customers' => 2,
            ],
            2 => [  // Starter (7 days, Monthly)
                'users' => 5,
                'branches' => 1,
                'roles' => 3,
                'categories' => 3,
                'products' => 300,
                'transactions' => 800,
                'expenditures' => 600,
                'customers' => 5,
            ],
            3 => [  // Professional (30 days, Monthly)
                'users' => 10,
                'branches' => 5,
                'roles' => 5,
                'categories' => 15,
                'products' => 500,
                'transactions' => 2000,
                'expenditures' => 1000,
                'customers' => 15,
            ],
            4 => [  // Enterprise (30 days, Monthly)
                'users' => PHP_INT_MAX,  // Unlimited
                'branches' => PHP_INT_MAX,
                'roles' => PHP_INT_MAX,
                'categories' => PHP_INT_MAX,
                'products' => PHP_INT_MAX,
                'transactions' => PHP_INT_MAX,
                'expenditures' => PHP_INT_MAX,
                'customers' => PHP_INT_MAX,
            ],
            5 => [  // Starter (Annual)
                'users' => 5,
                'branches' => 1,
                'roles' => 3,
                'categories' => 3,
                'products' => 300,
                'transactions' => 800,
                'expenditures' => 600,
                'customers' => 5,
            ],
            6 => [  // Professional (Annual)
                'users' => 10,
                'branches' => 5,
                'roles' => 5,
                'categories' => 15,
                'products' => 500,
                'transactions' => 2000,
                'expenditures' => 1000,
                'customers' => 15,
            ],
            7 => [  // Enterprise (Annual)
                'users' => PHP_INT_MAX,  // Unlimited
                'branches' => PHP_INT_MAX,
                'roles' => PHP_INT_MAX,
                'categories' => PHP_INT_MAX,
                'products' => PHP_INT_MAX,
                'transactions' => PHP_INT_MAX,
                'expenditures' => PHP_INT_MAX,
                'customers' => PHP_INT_MAX,
            ],
        ];

        // If the package_id is not defined or invalid, deny access
        if (!isset($packageLimits[$packageId])) {
            return response()->json(['message' => 'subscription issue, contact support'], 403);
        }

        $limits = $packageLimits[$packageId];

        // Get the current usage for specific entities based on the action being performed
        $currentUsage = [];

        // Check if the action corresponds to creating a resource
        if (in_array($routeName, ['create_user', 'create_branches', 'create_roles', 'create_categories', 'create_products', 'create_transactions', 'create_expenditures', 'create_customers'])) {
            $currentUsage = [
                'users' => \App\Models\User::count(),
                'branches' => \App\Models\Shop::count(),
                'roles' => \App\Models\Role::count(),
                'categories' => \App\Models\Category::count(),
                'products' => \App\Models\Product::count(),
                'transactions' => \App\Models\Transaction::count(),
                'expenditures' => \App\Models\Expenditure::count(),
                'customers' => \App\Models\Customer::count(),
            ];
        }

        // Map the routes to their respective entities
        $resourceMap = [
            'create_user' => 'users',
            'create_branches' => 'branches',
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
