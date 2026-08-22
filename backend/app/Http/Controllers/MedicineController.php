<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Notification;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MedicineController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    public function addMedicine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'name' => 'required|string',
            'category_medicine' => 'required|in:Antibiotics,Painkillers,Vitamins,Antidiabetics,Gastrointestinal,Respiratory,Cardiovascular,Dermatology',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'expire_date' => 'required|date',
            'manufacturer' => 'required|string',
            'reorder_level' => 'nullable|integer|min:0',
            // Barcodes were removed from the app. The column stays nullable so
            // existing rows and the catalogue seeders keep working.
            'qr_code' => 'nullable|numeric',
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);
        $validated['pharmacy_id'] = $pharmacy->id;
        $medicine = Medicine::create($validated);

        return response()->json([
            'message' => 'Medicine added Successfully',
            'medicine' => $medicine,
        ], 201);
    }

    public function getMedicines(Request $request): JsonResponse
    {
        $pharmacyId = $this->validatedPharmacyId($request);
        // Inventory means the whole shelf, including empty rows. The POS
        // filters unsellable stock client-side, while the inventory screen
        // needs zero-quantity rows for its Out of stock filter and restocking.
        $medicines = Medicine::where('pharmacy_id', $pharmacyId)->get();

        return response()->json(['medicines_count' => $medicines->count(), 'medicines' => $medicines]);
    }

    public function searchMedicine(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'name' => 'required|string',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;
        $medicines = Medicine::where('pharmacy_id', $pharmacyId)
            ->where('name', 'LIKE', '%'.$request->string('name').'%')
            ->where('quantity', '>', 0)
            ->get();

        return response()->json(['medicines' => $medicines]);
    }

    public function editMedicine(Request $request, int $id): JsonResponse
    {
        $medicine = Medicine::findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $medicine->pharmacy_id);
        Gate::forUser($request->user())->authorize('update', $medicine);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'category_medicine' => 'sometimes|in:Antibiotics,Painkillers,Vitamins,Antidiabetics,Gastrointestinal,Respiratory,Cardiovascular,Dermatology',
            'selling_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'expire_date' => 'sometimes|date',
            'manufacturer' => 'sometimes|string',
            'reorder_level' => 'sometimes|integer|min:0',
            'qr_code' => 'sometimes|numeric',
            'supplier_id' => 'sometimes|nullable|exists:suppliers,id',
        ]);

        $medicine->update($validated);

        return response()->json(['message' => 'Medicine updated Successfully', 'medicine' => $medicine]);
    }

    public function getExpiringMedicines(Request $request): JsonResponse
    {
        $pharmacyId = $this->validatedPharmacyId($request);
        $medicines = Medicine::where('pharmacy_id', $pharmacyId)
            ->whereDate('expire_date', '<=', now()->addMonths(3))
            ->whereDate('expire_date', '>=', now())
            ->get();

        foreach ($medicines as $medicine) {
            $this->notifyOnce($pharmacyId, 'expiry', 'تنبيه انتهاء صلاحية', 'دواء '.$medicine->name.' ينتهي قريباً', $medicine->name);
        }

        return response()->json(['expiring_count' => $medicines->count(), 'expiring_medicines' => $medicines]);
    }

    public function getLowStockMedicines(Request $request): JsonResponse
    {
        $pharmacyId = $this->validatedPharmacyId($request);
        $medicines = Medicine::where('pharmacy_id', $pharmacyId)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->get();

        foreach ($medicines as $medicine) {
            $this->notifyOnce(
                $pharmacyId,
                'low_stock',
                'تنبيه نقص مخزون',
                'دواء '.$medicine->name.' كميته أصبحت '.$medicine->quantity.' فقط',
                $medicine->name
            );
        }

        return response()->json(['low_stock_count' => $medicines->count(), 'low_stock_medicines' => $medicines]);
    }

    public function getOutOfStockMedicines(Request $request): JsonResponse
    {
        $pharmacyId = $this->validatedPharmacyId($request);
        $medicines = Medicine::where('pharmacy_id', $pharmacyId)->where('quantity', 0)->get();

        return response()->json(['out_of_stock_count' => $medicines->count(), 'out_of_stock_medicines' => $medicines]);
    }

    public function getMedicinesByCategory(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'category' => 'required|string',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;
        $medicines = Medicine::where('pharmacy_id', $pharmacyId)
            ->where('category_medicine', $request->string('category'))
            ->get();

        return response()->json(['medicines' => $medicines]);
    }

    private function validatedPharmacyId(Request $request): int
    {
        $request->validate(['pharmacy_id' => 'required|exists:pharmacies,id']);

        return $this->pharmacyContext->resolve($request)->id;
    }

    private function notifyOnce(int $pharmacyId, string $type, string $title, string $message, string $medicineName): void
    {
        $alreadyNotified = Notification::where('pharmacy_id', $pharmacyId)
            ->where('type', $type)
            ->where('message', 'LIKE', '%'.$medicineName.'%')
            ->exists();

        if (! $alreadyNotified) {
            Notification::create([
                'pharmacy_id' => $pharmacyId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => false,
                'date' => now(),
            ]);
        }
    }
}
