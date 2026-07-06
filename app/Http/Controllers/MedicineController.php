<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use App\Models\Notification;

class MedicineController extends Controller
{
    public function addMedicine(Request $request)
    {
        $request->validate([
            'pharmacy_id'       => 'required|exists:pharmacies,id',
            'name'              => 'required|string',
            'category_medicine' => 'required|in:Antibiotics,Painkillers,Vitamins,Antidiabetics,Gastrointestinal,Respiratory,Cardiovascular,Dermatology',
            'selling_price'     => 'required|numeric',
            'cost_price'        => 'required|numeric',
            'quantity'          => 'required|integer',
            'expire_date'       => 'required|date',
            'manufacturer'      => 'required|string',
            'reorder_level'     => 'nullable|integer',
            'qr_code'           => 'required|numeric',
        ]);

        $medicine = Medicine::create($request->all());

        return response()->json([
            'message'  => 'Medicine added Successfully',
            'medicine' => $medicine,
        ], 201);
    }

    public function getMedicines(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $medicines = Medicine::where('pharmacy_id', $request->pharmacy_id)
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'medicines_count' => $medicines->count(),
            'medicines'       => $medicines,
        ]);
    }

    public function searchMedicine(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'name'        => 'required|string',
        ]);

        $medicines = Medicine::where('pharmacy_id', $request->pharmacy_id)
            ->where('name', 'LIKE', '%' . $request->name . '%')
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'medicines' => $medicines,
        ]);
    }

    public function editMedicine(Request $request, $id)
    {
        $medicine = Medicine::findOrFail($id);

        $request->validate([
            'name'              => 'sometimes|string',
            'category_medicine' => 'sometimes|in:Antibiotics,Painkillers,Vitamins,Antidiabetics,Gastrointestinal,Respiratory,Cardiovascular,Dermatology',
            'selling_price'     => 'sometimes|numeric',
            'cost_price'        => 'sometimes|numeric',
            'quantity'          => 'sometimes|integer',
            'expire_date'       => 'sometimes|date',
            'manufacturer'      => 'sometimes|string',
            'reorder_level'     => 'sometimes|integer',
            'qr_code'           => 'sometimes|numeric',
        ]);

        $medicine->update($request->all());

        return response()->json([
            'message'  => 'Medicine updated Successfully',
            'medicine' => $medicine,
        ]);
    }

    public function getExpiringMedicines(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $threeMonthsLater = now()->addMonths(3);

        $medicines = Medicine::where('pharmacy_id', $request->pharmacy_id)
            ->whereDate('expire_date', '<=', $threeMonthsLater)
            ->whereDate('expire_date', '>=', now())
            ->get();

        foreach ($medicines as $medicine) {
            $alreadyNotified = Notification::where('pharmacy_id', $request->pharmacy_id)
                ->where('type', 'expiry')
                ->where('message', 'LIKE', '%' . $medicine->name . '%')
                ->exists();

            if (!$alreadyNotified) {
                Notification::create([
                    'pharmacy_id' => $request->pharmacy_id,
                    'title'       => 'تنبيه انتهاء صلاحية',
                    'message'     => 'دواء ' . $medicine->name . ' ينتهي قريباً',
                    'type'        => 'expiry',
                    'is_read'     => false,
                    'date'        => now(),
                ]);
            }
        }

        return response()->json([
            'expiring_count'     => $medicines->count(),
            'expiring_medicines' => $medicines,
        ]);
    }

    public function getLowStockMedicines(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $medicines = Medicine::where('pharmacy_id', $request->pharmacy_id)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->get();

        foreach ($medicines as $medicine) {
            $alreadyNotified = Notification::where('pharmacy_id', $request->pharmacy_id)
                ->where('type', 'low_stock')
                ->where('message', 'LIKE', '%' . $medicine->name . '%')
                ->exists();

            if (!$alreadyNotified) {
                Notification::create([
                    'pharmacy_id' => $request->pharmacy_id,
                    'title'       => 'تنبيه نقص مخزون',
                    'message'     => 'دواء ' . $medicine->name . ' كميته أصبحت ' . $medicine->quantity . ' فقط',
                    'type'        => 'low_stock',
                    'is_read'     => false,
                    'date'        => now(),
                ]);
            }
        }

        return response()->json([
            'low_stock_count'     => $medicines->count(),
            'low_stock_medicines' => $medicines,
        ]);
    }

    public function getOutOfStockMedicines(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $medicines = Medicine::where('pharmacy_id', $request->pharmacy_id)
            ->where('quantity', 0)
            ->get();

        return response()->json([
            'out_of_stock_count'     => $medicines->count(),
            'out_of_stock_medicines' => $medicines,
        ]);
    }
    public function getMedicinesByCategory(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'category'    => 'required|string',
        ]);

        $medicines = Medicine::where('pharmacy_id', $request->pharmacy_id)
            ->where('category_medicine', $request->category)
            ->get();

        return response()->json([
            'medicines' => $medicines,
        ]);
    }
}
