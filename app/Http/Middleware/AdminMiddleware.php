<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 👇 فحص سريع مؤقت: للتأكد أن بوستمان يرسل الـ Headers بشكل صحيح والسيرفر يستقبلها
       // return response()->json(['msg' => 'وصلت لميدل وير الآدمن بنجاح وبوستمان سليم']);

        try {
            $adminKey = $request->header('X-Admin-Key');
            $expectedKey = env('ADMIN_KEY');

            if (!$adminKey || $adminKey !== $expectedKey) {
                return response()->json([
                    'message' => 'Unauthorized. Admin key is missing or invalid.'
                ], 401);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server Error in Admin Middleware.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
