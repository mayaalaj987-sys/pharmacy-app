<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 👇 فحص سريع مؤقت: إذا رجع لك هذا الرد في بوستمان، فالسيرفر سليم والمشكلة بقاعدة البيانات
         return response()->json(['msg' => 'وصلت لميدل وير الأدوار بنجاح قبل فحص الـ DB']);

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            if (!in_array($user->role, $roles)) {
                return response()->json([
                    'message' => 'Unauthorized. This action requires a specific role.'
                ], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            // حماية لمنع انهيار السيرفر (ECONNRESET) في حال حدوث خطأ أثناء فحص التوكن
            return response()->json([
                'message' => 'Unauthenticated or Token Invalid.',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}
