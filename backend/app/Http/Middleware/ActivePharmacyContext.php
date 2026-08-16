<?php

namespace App\Http\Middleware;

use App\Services\PharmacyContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivePharmacyContext
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(
            'client_supplied_pharmacy_id',
            $request->request->has('pharmacy_id'),
        );
        $pharmacy = $this->pharmacyContext->resolve($request);

        // Keep legacy controller validation working while the canonical client
        // contract moves pharmacy context to X-Pharmacy-Id.
        $request->merge(['pharmacy_id' => $pharmacy->id]);
        $request->attributes->set('active_pharmacy', $pharmacy);

        return $next($request);
    }
}
