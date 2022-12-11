<?php

namespace App\Interfaces;

interface ProductRepositoryInterface{
    public function create_product($request);
    public function update_product($request, $id);
    public function delete_product($id);
    public function generate_product_report($request, $id);
    public function create_category($request);
    public function update_category($request, $id);
    public function delete_category($id);
    // PURCHASE
    public function new_purchase($request);
    public function update_purchase($request, $id);
    public function delete_purchase_detail($id);
    public function delete_purchase($id);
}
