<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return response()->json(Order::orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'required|string|max:50',
            'total_amount'   => 'required|numeric',
            'items'          => 'required|array',
            'status'         => 'nullable|string',
        ]);

        $order = Order::create([
            'customer_name'  => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_phone' => $validated['customer_phone'],
            'total_amount'   => $validated['total_amount'],
            'items'          => $validated['items'],
            'status'         => $validated['status'] ?? 'pending',
        ]);

        return response()->json(['message' => 'Order created successfully', 'order' => $order], 201);
    }

    public function show($id)
    {
        $order = Order::findOrFail($id);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $order->update($validated);

        return response()->json(['message' => 'Order updated successfully', 'order' => $order]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }
}
