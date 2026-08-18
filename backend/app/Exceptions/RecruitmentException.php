<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A recruitment step that cannot be taken right now.
 *
 * Always a conflict with the state of the world rather than a bad request: the
 * shift filled up, the applicant took another job, the pharmacy was suspended.
 * Renders itself so the service can refuse without every caller re-mapping the
 * same handful of codes.
 */
class RecruitmentException extends RuntimeException
{
    public function __construct(
        string $message,
        // Not $code: Exception already declares that, and it is an int there.
        public readonly string $errorCode,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->status);
    }
}
