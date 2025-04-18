<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /**
     * Display a listing of the budgets.
     */
    public function index()
    {
        $budgets = Budget::all();
        return response()->json(['data' => $budgets], 200);
    }

    /**
     * Store a newly created budget in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'budget_type' => 'required|in:expenditure,revenue',
            'budget_amount' => 'required|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'expenditure_type' => 'nullable|integer',
            'month' => 'required|string|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'year' => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $budget = Budget::create($request->all());
        return response()->json(['data' => $budget, 'message' => 'Budget created successfully'], 201);
    }

    /**
     * Store multiple budgets in storage.
     */
    public function storeBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'budgets' => 'required|array|min:1',
            'budgets.*.budget_type' => 'required|in:expenditure,revenue',
            'budgets.*.budget_amount' => 'required|numeric|min:0',
            'budgets.*.category_id' => 'nullable|integer|exists:categories,id',
            'budgets.*.expenditure_type' => 'nullable|integer',
            'budgets.*.month' => 'required|string|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'budgets.*.year' => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shopId = $request->query('shop_id');

        $budgets = $request->input('budgets');
        $errors = [];
        $createdBudgets = [];

        // Group budgets by month and year to check constraints
        $groupedBudgets = [];
        foreach ($budgets as $index => $budget) {
            $key = $budget['month'] . '-' . $budget['year'];
            $groupedBudgets[$key][] = ['index' => $index, 'data' => $budget];
        }

        // Check uniqueness constraints
        foreach ($groupedBudgets as $key => $group) {
            $expenditureTypes = [];
            $revenueCategoryIds = [];

            foreach ($group as $item) {
                $budget = $item['data'];
                $index = $item['index'];

                // Check existing records in the database for this month-year
                $existing = Budget::where('month', $budget['month'])
                    ->where('year', $budget['year'])
                    ->get();

                foreach ($existing as $record) {
                    if ($record->budget_type === 'expenditure' && $record->expenditure_type !== null) {
                        $expenditureTypes[] = $record->expenditure_type;
                    } elseif ($record->budget_type === 'revenue' && $record->category_id !== null) {
                        $revenueCategoryIds[] = $record->category_id;
                    }
                }

                // Check within the incoming batch
                if ($budget['budget_type'] === 'expenditure' && $budget['expenditure_type'] !== null) {
                    if (in_array($budget['expenditure_type'], $expenditureTypes)) {
                        $errors[] = "Budget at index $index: Expenditure type {$budget['expenditure_type']} already budgeted for {$budget['month']} {$budget['year']}.";
                        continue;
                    }
                    $expenditureTypes[] = $budget['expenditure_type'];
                } elseif ($budget['budget_type'] === 'revenue' && $budget['category_id'] !== null) {
                    if (in_array($budget['category_id'], $revenueCategoryIds)) {
                        $errors[] = "Budget at index $index: Revenue category ID {$budget['category_id']} already budgeted for {$budget['month']} {$budget['year']}.";
                        continue;
                    }
                    $revenueCategoryIds[] = $budget['category_id'];
                }
            }
        }

        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        // Create budgets within a transaction
        DB::beginTransaction();
        try {
            foreach ($budgets as $budget) {
                $budget['shop_id'] = $shopId; // Inject shop_id into each record
                $createdBudget = Budget::create($budget);
                $createdBudgets[] = $createdBudget;
            }
            DB::commit();
            return response()->json(['data' => $createdBudgets, 'message' => 'Budgets created successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create budgets: ' . $e->getMessage()], 500);
        }
        
    }

    /**
     * Display budgets grouped by period (month and year).
     */
    public function showPeriodically(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_month' => 'sometimes|string|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'start_year' => 'sometimes|string|digits:4',
            'end_month' => 'sometimes|string|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'end_year' => 'sometimes|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Budget::query();

        // Apply filters for start and end period if provided
        if ($request->has('start_month') && $request->has('start_year')) {
            $query->whereRaw("CONCAT(year, '-', CASE 
                WHEN month = 'January' THEN '01'
                WHEN month = 'February' THEN '02'
                WHEN month = 'March' THEN '03'
                WHEN month = 'April' THEN '04'
                WHEN month = 'May' THEN '05'
                WHEN month = 'June' THEN '06'
                WHEN month = 'July' THEN '07'
                WHEN month = 'August' THEN '08'
                WHEN month = 'September' THEN '09'
                WHEN month = 'October' THEN '10'
                WHEN month = 'November' THEN '11'
                WHEN month = 'December' THEN '12'
            END) >= ?", [$request->start_year . '-' . $this->monthToNumber($request->start_month)]);
        }

        if ($request->has('end_month') && $request->has('end_year')) {
            $query->whereRaw("CONCAT(year, '-', CASE 
                WHEN month = 'January' THEN '01'
                WHEN month = 'February' THEN '02'
                WHEN month = 'March' THEN '03'
                WHEN month = 'April' THEN '04'
                WHEN month = 'May' THEN '05'
                WHEN month = 'June' THEN '06'
                WHEN month = 'July' THEN '07'
                WHEN month = 'August' THEN '08'
                WHEN month = 'September' THEN '09'
                WHEN month = 'October' THEN '10'
                WHEN month = 'November' THEN '11'
                WHEN month = 'December' THEN '12'
            END) <= ?", [$request->end_year . '-' . $this->monthToNumber($request->end_month)]);
        }
        $shopId = $request->query('shop_id');
        if ($shopId == 0) {
            $budgets = $query->where->with('shop')->with('category')->orderBy('year', 'asc')
            ->orderByRaw("FIELD(month, 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December')")
            ->get();   
        } else {
            $budgets = $query->where('shop_id', $shopId)->with('shop')->with('category')->orderBy('year', 'asc')
                        ->orderByRaw("FIELD(month, 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December')")
                        ->get();
        }
        // Group budgets by year and month
        $groupedBudgets = $budgets->groupBy('year')->map(function ($yearGroup) {
            return $yearGroup->groupBy('month')->map(function ($monthGroup) {
                return $monthGroup->values();
            });
        });

        return response()->json(['data' => $groupedBudgets], 200);
    }

    /**
     * Convert month name to number.
     */
    private function monthToNumber($month)
    {
        $months = [
            'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
            'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
            'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12'
        ];
        return $months[$month];
    }

    /**
     * Display the specified budget.
     */
    public function show($id)
    {
        $budget = Budget::findOrFail($id);
        return response()->json(['data' => $budget], 200);
    }

    /**
     * Update the specified budget in storage.
     */
    public function update(Request $request, $id)
    {
        $budget = Budget::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'budget_type' => 'sometimes|in:expenditure,revenue',
            'budget_amount' => 'sometimes|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'expenditure_type' => 'nullable|integer',
            'month' => 'sometimes|string|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'year' => 'sometimes|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $budget->update($request->all());
        return response()->json(['data' => $budget, 'message' => 'Budget updated successfully'], 200);
    }

    /**
     * Remove the specified budget from storage.
     */
    public function destroy($id)
    {
        $budget = Budget::findOrFail($id);
        $budget->delete();
        return response()->json(['message' => 'Budget deleted successfully'], 200);
    }
}