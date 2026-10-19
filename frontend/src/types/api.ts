// Shapes shared by every API call. Import these; don't redeclare them in components.

// Every API response: { status: { code, message }, data }
export type ApiResponse<T> = {
  status: { code: number; message: string };
  data: T;
};

// `data` when validation fails (status.code 3), e.g. { email: ["The email field is required."] }
export type FieldErrors = Record<string, string[]>;
