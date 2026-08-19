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

    /**
     * Leaves or revises this pharmacist's rating of the app.
     *
     * Revisable, where it used to refuse a second attempt outright. Holding
     * somebody to one bad afternoon forever is not feedback, and it also made
     * the note unreachable for everyone who had already left a star.
     */
    public function submitRating(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pharmacist_id' => 'required|exists:pharmacists,id',
            'stars' => 'required|integer|min:1|max:5',
            // What actually happened. A star says somebody was unhappy; this is
            // the only part anyone can act on.
            'note' => 'nullable|string|max:1000',
        ]);
        $pharmacist = $request->user();

        if (! $pharmacist instanceof Pharmacist || (int) $request->pharmacist_id !== (int) $pharmacist->id) {
            throw new AuthorizationException('The pharmacist identifier does not match the authenticated user.');
        }

        $existing = Rating::where('pharmacist_id', $pharmacist->id)->first();

        $rating = Rating::updateOrCreate(
            ['pharmacist_id' => $pharmacist->id],
            [
                'stars' => $validated['stars'],
                'note' => $validated['note'] ?? null,
                'date' => now()->toDateString(),
            ],
        );

        return response()->json([
            'message' => $existing
                ? 'Your rating has been updated.'
                : 'Thanks — your rating has been recorded.',
            'code' => $existing ? 'rating_updated' : 'rating_recorded',
            'rating' => $rating,
        ], $existing ? 200 : 201);
    }
}
