<?php

namespace App\Interfaces;

interface ReportRepositoryInterface{
    public function weekly_report($request);
    public function daily_report($request);
    public function monthly_report($request);
    public function annual_report($request);

    public function staff_sales_report($request);
}
