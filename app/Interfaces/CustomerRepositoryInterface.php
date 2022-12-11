<?php

namespace App\Interfaces;

interface CustomerRepositoryInterface{
    public function create_customer($request);
    public function update_customer($request, $id);
    public function fund_customer($request, $id);
    public function delete_customer($id);
    public function customer_details($id);
}
