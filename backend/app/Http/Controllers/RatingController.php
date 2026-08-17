<?php

namespace App\Http\Controllers;

use App\Models\Pharmacist;
use App\Models\Rating;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    /**
     * The signed-in pharmacist's own app rating, plus the overall average.
     * Lets the client show existing state instead of attempting a duplicate.
     */
    public function myRating(Request $request): JsonResponse
    {
        $pharmacist = $request->user();

        if (! $pharmacist instanceof Pharmacist) {
            throw new AuthorizationException('Only a pharmacist can read an app rating.');
        }

        $rating = Rating::where('pharmacist_id', $pharmacist->id)->first();

        return response()->json([
            'rating' => $rating,
            'has_rated' => $rating !== null,
            'average_stars' => round((float) Rating::avg('stars'), 2),
            'ratings_count' => Rating::count(),
        ]);
    }

    public function submitRating(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacist_id' => 'required|exists:pharmacists,id',
            'stars' => 'required|integer|min:1|max:5',
        ]);
        $pharmacist = $request->user();

        if (! $pharmacist instanceof Pharmacist || (int) $request->pharmacist_id !== (int) $pharmacist->id) {
            throw new AuthorizationException('The pharmacist identifier does not match the authenticated user.');
        }

        $alreadyRated = Rating::where('pharmacist_id', $pharmacist->id)
            ->exists();

        if ($alreadyRated) {
            return response()->json([
                'message' => 'You have already rated the app',
            ], 400);
        }

        $rating = Rating::create([
            'pharmacist_id' => $pharmacist->id,
            'stars' => $request->stars,
            'date' => now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating' => $rating,
        ], 201);
    }
}
