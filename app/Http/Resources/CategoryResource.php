<?php

namespace App\Http\Resources;

use App\Traits\CategoryTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    use CategoryTrait;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        // return parent::toArray($request);

        return [
            "id"=> $this->id,
            "name" => $this->name,
            "products" => $this->get_product_images($this->products)
        ];

    }
}
