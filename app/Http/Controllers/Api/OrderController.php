<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index()
    {
        return response()->json(Order::orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'        => 'required|string|max:255',
            'customer_email'       => 'nullable|email|max:255',
            'customer_phone'       => 'required|string|max:50',
            'items'                => 'required|array|min:1|max:50',
            'items.*.id'           => 'nullable',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.option_ids'   => 'nullable|array',
            'items.*.option_ids.*' => 'integer',
            'items.*.quantity'     => 'required|integer|min:1|max:100',
            'items.*.price_on_request' => 'nullable|boolean',
        ]);

        // Prices, titles and the total are always computed here from the database;
        // anything price-related sent by the browser is ignored.
        $items = [];
        $total = 0;
        foreach ($validated['items'] as $i => $item) {
            $line = $this->priceLine($item);
            if (is_string($line)) {
                throw ValidationException::withMessages(["items.$i" => $line]);
            }
            $items[] = $line;
            $total += $line['price'] * $line['quantity'];
        }

        $order = Order::create([
            'customer_name'  => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_phone' => $validated['customer_phone'],
            'total_amount'   => round($total, 2),
            'items'          => $items,
            'status'         => 'pending',
        ]);

        return response()->json(['message' => 'Order created successfully', 'order' => $order], 201);
    }

    /**
     * Build one stored order line from a cart item, or return an error message.
     */
    private function priceLine(array $item): array|string
    {
        // Older carts only have "id" (e.g. 12 or "12-1728000000000"); placeholder
        // items like "fav-1" have no product behind them and are rejected.
        $productId = $item['product_id'] ?? null;
        if (!$productId && preg_match('/^\d+/', (string) ($item['id'] ?? ''), $m)) {
            $productId = (int) $m[0];
        }

        $product = $productId
            ? Product::with('attributes.values')->where('is_active', true)->find($productId)
            : null;
        if (!$product) {
            return 'Some items in your bag are no longer available. Please remove them and try again.';
        }

        $unitPrice = (float) ($product->discount_price > 0 ? $product->discount_price : $product->base_price);

        $optionsById = [];
        foreach ($product->attributes as $attribute) {
            foreach ($attribute->values as $value) {
                $optionsById[$value->id] = [$attribute, $value];
            }
        }

        $chosen = [];
        $perAttribute = [];
        foreach (array_unique($item['option_ids'] ?? []) as $optionId) {
            if (!isset($optionsById[$optionId])) {
                return "The options chosen for \"{$product->title}\" have changed. Please remove it and add it to your bag again.";
            }
            [$attribute, $value] = $optionsById[$optionId];
            $perAttribute[$attribute->id] = ($perAttribute[$attribute->id] ?? 0) + 1;
            if ($attribute->type !== 'checkbox' && $perAttribute[$attribute->id] > 1) {
                return "Only one \"{$attribute->name}\" can be chosen for \"{$product->title}\".";
            }
            $unitPrice += (float) $value->price_modifier;
            $chosen[] = $value->value;
        }

        $image = (string) ($product->image ?: ($product->images[0] ?? ''));
        // "Contact for details" on a festive Hidden item: no price shown, the shop quotes it
        // over WhatsApp. Only honoured for products the admin actually set to Hidden.
        $priceOnRequest = $product->is_festive_hidden && !empty($item['price_on_request']);

        return [
            'product_id'       => $product->id,
            'option_ids'       => array_values(array_unique($item['option_ids'] ?? [])),
            'title'            => $product->title . ($chosen ? ' (' . implode(', ', $chosen) . ')' : ''),
            'price'            => $priceOnRequest ? 0 : round(max(0, $unitPrice), 2),
            'price_on_request' => $priceOnRequest,
            'quantity'         => (int) $item['quantity'],
            'image'            => str_starts_with($image, 'data:') ? '' : $image,
        ];
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

    public function track(Request $request)
    {
        // Both the order ID and the phone used at checkout are required, so a
        // lookup can only ever return the caller's own order.
        $orderId = preg_replace('/[^0-9]/', '', (string) $request->input('order_id'));
        $phone = preg_replace('/[^0-9]/', '', (string) $request->input('phone'));

        if ($orderId === '' || strlen($phone) < 7) {
            return response()->json(['message' => 'Please provide your Order ID and the Phone Number used for the order.'], 422);
        }

        $order = Order::find((int) $orderId);
        $storedPhone = $order ? preg_replace('/[^0-9]/', '', $order->customer_phone) : '';

        // Compare the last 10 digits so "+91 98765 43210" matches "9876543210"
        if (!$order || substr($storedPhone, -10) !== substr($phone, -10)) {
            return response()->json(['message' => 'No order found for this Order ID and Phone Number.'], 404);
        }

        return response()->json([[
            'id' => $order->id,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'created_at' => $order->created_at,
            'items' => collect($order->items ?? [])->map(fn ($item) => [
                'title' => $item['title'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'price' => $item['price'] ?? 0,
                'price_on_request' => $item['price_on_request'] ?? false,
            ])->values(),
        ]]);
    }
}
