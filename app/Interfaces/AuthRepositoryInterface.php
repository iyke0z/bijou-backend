<?php

namespace App\Interfaces;

interface AuthRepositoryInterface{
    public function login($request);
    public function logout();
    public function create_business_details($request);
    public function update_business_details($request, $id);
    public function delete_business_details($id);
    public function generate_user_codes($request);

}
