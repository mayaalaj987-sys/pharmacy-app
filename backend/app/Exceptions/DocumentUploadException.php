<?php

namespace App\Exceptions;

use RuntimeException;

class DocumentUploadException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'document_invalid',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->status);
    }
}
