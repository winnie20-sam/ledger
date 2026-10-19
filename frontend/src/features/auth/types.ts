// Types used only by the auth feature (login, register).

export type User = {
  id: number;
  name: string;
  email: string;
};

// `data` after a successful login or register: both return the same thing
export type AuthData = {
  access_token: string;
  token_type: string;
  expires_in: number; // seconds
  user: User;
};
