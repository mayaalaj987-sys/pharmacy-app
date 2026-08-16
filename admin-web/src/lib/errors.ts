export class AdminApiError extends Error {
  readonly code: string;
  readonly status: number;
  readonly errors?: Record<string, string[]>;
  readonly requestId?: string;

  constructor(
    message: string,
    code: string,
    status: number,
    errors?: Record<string, string[]>,
    requestId?: string,
  ) {
    super(message);
    this.name = "AdminApiError";
    this.code = code;
    this.status = status;
    this.errors = errors;
    this.requestId = requestId;
  }
}

/** Thrown when a request is aborted (navigation away, logout, unmount). Callers should treat this as "no-op", not an error to display. */
export class AdminApiAbortError extends Error {
  constructor() {
    super("Request was cancelled.");
    this.name = "AdminApiAbortError";
  }
}
