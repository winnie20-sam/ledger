// The one place the app reads its settings from, like Angular's environment.ts.
// Import it anywhere:  import { environment } from "../../environments/environment";
//
// The values come from .env.development (npm run dev) or .env.production (npm run build).
// ⚠️ Everything here ends up in the browser bundle, where anyone can read it. Never put secrets here.

export const environment = {
  production: import.meta.env.PROD,
  apiUrl: import.meta.env.VITE_API_URL,
};

if (!environment.apiUrl) {
  throw new Error("VITE_API_URL is not set. Add it to .env.development and restart `npm run dev`.");
}
