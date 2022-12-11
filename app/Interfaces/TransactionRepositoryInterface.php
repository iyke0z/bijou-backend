<?php

namespace App\Interfaces;

interface TransactionRepositoryInterface{
    public function sell($request);
    public function update_sale($request);
    public function delete_sale($id);
    public function create_discount($request);
    public function update_discount($request, $id);
    public function delete_discount($id);
    public function customer_discount($request, $id);
    public function delete_customer_discount($id, $discount);
    public function drinks_prep_status();
    public function food_prep_status();
    public function update_prep_status($request);
    public function pay($request);
}
