<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoodsDeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class GoodDeliverNoteController extends Controller
{
    /**
     * Create a new goods delivery note.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $deliveryNotes = GoodsDeliveryNote::with('transaction')->with('user')->with('customer')->get();

        return response()->json([
            'message' => 'Goods delivery notes retrieved successfully.',
            'data' => $deliveryNotes,
        ], 200);
    }
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|integer',
            'date_left_warehouse' => 'nullable|string|max:255',
            'delivery_details' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'proccessed_by' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $deliveryNote = GoodsDeliveryNote::create($request->only([
            'transaction_id',
            'date_left_warehouse',
            'delivery_details',
            'note',
            'proccessed_by'
        ]));

        return response()->json([
            'message' => 'Goods delivery note created successfully.',
            'data' => $deliveryNote,
        ], 201);
    }

    /**
     * Display the specified goods delivery note.
     *
     * @param GoodsDeliveryNote $goodsDeliveryNote
     * @return JsonResponse
     */
    public function show(GoodsDeliveryNote $goodsDeliveryNote): JsonResponse
    {
        return response()->json([
            'data' => $goodsDeliveryNote,
        ], 200);
    }

    /**
     * Update the specified goods delivery note.
     *
     * @param Request $request
     * @param GoodsDeliveryNote $goodsDeliveryNote
     * @return JsonResponse
     */
    public function update(Request $request, GoodsDeliveryNote $goodsDeliveryNote): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|integer',
            'date_left_warehouse' => 'nullable|string|max:255',
            'delivery_details' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'proccessed_by' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $goodsDeliveryNote->update($request->only([
            'transaction_id',
            'date_left_warehouse',
            'delivery_details',
            'note',
            'proccessed_by'
        ]));

        return response()->json([
            'message' => 'Goods delivery note updated successfully.',
            'data' => $goodsDeliveryNote->fresh(),
        ], 200);
    }

    /**
     * Download the specified goods delivery note as a PDF.
     *
     * @param GoodsDeliveryNote $goodsDeliveryNote
     * @return \Illuminate\Http\Response
     */
    public function download(GoodsDeliveryNote $goodsDeliveryNote): \Illuminate\Http\Response
    {
        $data = [
            'goodsDeliveryNote' => $goodsDeliveryNote,
            'title' => 'Goods Delivery Note #' . $goodsDeliveryNote->id,
        ];

        $pdf = Pdf::loadView('goods_delivery_notes.pdf', $data);
        return $pdf->download('goods_delivery_note_' . $goodsDeliveryNote->id . '.pdf');
    }
}