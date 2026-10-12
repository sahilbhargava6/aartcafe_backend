<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

/**
 * The footer's "Stay Connected" sign-ups: anyone can subscribe, the dashboard lists and removes them.
 */
class SubscriberController extends Controller
{
    public function index()
    {
        return response()->json(Subscriber::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email|max:255']);

        // Signing up twice with the same address is fine; it is stored once
        $subscriber = Subscriber::firstOrCreate(['email' => mb_strtolower(trim($validated['email']))]);

        return response()->json([
            'message' => $subscriber->wasRecentlyCreated ? 'Subscribed' : 'Already subscribed',
        ], $subscriber->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Subscriber $subscriber)
    {
        $subscriber->delete();
        return response()->json(['message' => 'Subscriber removed']);
    }
}
