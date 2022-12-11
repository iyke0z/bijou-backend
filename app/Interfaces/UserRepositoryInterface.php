<?php

namespace App\Interfaces;

interface UserRepositoryInterface{
    public function create_user($request);
    public function update_user($request, $id);
    public function assign_user_priviledge($request, $id);
    public function assign_role_priviledge($request, $id);
    public function delete_user($id);
    public function create_role($request);
    public function delete_role($id);
    public function create_priviledge($request);
    public function delete_priviledge($id);
    public function get_user($id);
}
