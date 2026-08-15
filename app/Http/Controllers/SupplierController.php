<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Medicine;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    // ===== عرض كل الموردين =====
    public function getSuppliers(): \Illuminate\Http\JsonResponse
    {
        $suppliers = Supplier::all();

        return response()->json([
            'suppliers' => $suppliers,
        ]);
    }

    // ===== عرض أدوية مورد معين =====
    public function getSupplierMedicines($id): \Illuminate\Http\JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        // Supplier catalogue rows are global. Tenant inventory rows must not leak here.
        $medicines = Medicine::where('supplier_id', $id)
            ->whereNull('pharmacy_id')
            ->get();

        return response()->json([
            'supplier'  => $supplier->name,
            'medicines' => $medicines,
        ]);
    }
}
