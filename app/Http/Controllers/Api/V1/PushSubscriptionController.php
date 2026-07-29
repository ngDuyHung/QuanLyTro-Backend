<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
  // React gọi API này khi xin được quyền thông báo
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $request->endpoint], // Tìm theo endpoint
            [
                'user_id' => $request->user()->id,
                'public_key' => $request->input('keys.p256dh'),
                'auth_token' => $request->input('keys.auth'),
            ]
        );

        return response()->json(['message' => 'Subscribed successfully.']);
    }

    // Tùy chọn: React gọi API này khi user đăng xuất hoặc tắt thông báo
    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required|string']);
        
        PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Unsubscribed successfully.']);
    }
}
