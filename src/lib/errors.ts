export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

export function notFound(message = "Not Found."): ApiError {
  return new ApiError(404, message);
}

export function unauthorized(message = "Unauthorized."): ApiError {
  return new ApiError(401, message);
}

export function unprocessable(message: string): ApiError {
  return new ApiError(422, message);
}

export function tooManyRequests(message = "Too Many Attempts."): ApiError {
  return new ApiError(429, message);
}

/** Mirrors Laravel's default 422 validation error shape: {message, errors}. */
export function validationError(errors: Record<string, string[]>): ApiError {
  return new ApiError(422, "The given data was invalid.", errors);
}
