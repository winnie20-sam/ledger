/// <reference types="vite/client" />

// Types for the variables in .env.* files, so import.meta.env.VITE_API_URL is a string, not `any`.
// Add a line here every time you add a VITE_ variable.
interface ImportMetaEnv {
  readonly VITE_API_URL: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
