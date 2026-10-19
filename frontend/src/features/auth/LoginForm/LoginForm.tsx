import { useState, type FormEvent } from "react";
import { environment } from "../../../environments/environment";
import type { ApiResponse, FieldErrors } from "../../../types/api";
import type { AuthData } from "../types";
// This component's own styles. `styles.card` is the scoped class name for .card in the file.
import styles from "./LoginForm.module.scss";

export default function LoginForm() {
  // 1. STATE: everything that can change goes here, BEFORE the return.
  //    Form values:
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  //    Request status:
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(""); // general message, e.g. "Invalid email or password"
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({}); // per-field messages
  const [userName, setUserName] = useState(""); // filled in once logged in

  // 2. EVENT HANDLERS: plain functions inside the component.
  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault(); // stop the browser reloading the page (Angular's ngSubmit does this for you)

    setIsSubmitting(true);
    setError("");
    setFieldErrors({});

    try {
      const response = await fetch(`${environment.apiUrl}/users/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ email, password }),
      });
      const body: ApiResponse<unknown> = await response.json();

      // This API returns HTTP 200 even on failure, so check status.code, not response.ok
      if (body.status.code === 1) {
        const data = body.data as AuthData;
        setUserName(data.user.name);
        // Step 3: save data.access_token so other requests can use it
      } else if (body.status.code === 3) {
        setFieldErrors(body.data as FieldErrors);
      } else {
        setError(body.status.message);
      }
    } catch {
      // fetch only throws when the server can't be reached at all
      setError("Could not reach the server. Is the API running?");
    } finally {
      setIsSubmitting(false); // runs whether it succeeded or failed
    }
  }

  // 3. EARLY RETURN: show something different once logged in (Angular: *ngIf on the whole template)
  if (userName) {
    return (
      <div className={styles.page}>
        <p className={styles.success}>Logged in as {userName}</p>
      </div>
    );
  }

  // 4. JSX: the markup. Use { } to put JavaScript values in it.
  //    className={styles.x} is React's version of class="x" with Angular's style encapsulation.
  return (
    <div className={styles.page}>
      {/* noValidate: skip the browser's own popups so we see the API's validation messages */}
      <form className={styles.card} onSubmit={handleSubmit} noValidate>
        <div className={styles.header}>
          <h1 className={styles.title}>Log in</h1>
          <p className={styles.subtitle}>Welcome back to Ledger.</p>
        </div>

        {/* Only renders when error isn't empty (Angular: *ngIf="error") */}
        {error && (
          <p role="alert" className={styles.alert}>
            {error}
          </p>
        )}

        <div className={styles.field}>
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            aria-invalid={!!fieldErrors.email} // red border when the API rejects it (see index.scss)
          />
          {fieldErrors.email && <p className={styles.fieldError}>{fieldErrors.email[0]}</p>}
        </div>

        <div className={styles.field}>
          <label htmlFor="password">Password</label>
          <input
            id="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            aria-invalid={!!fieldErrors.password}
          />
          {fieldErrors.password && <p className={styles.fieldError}>{fieldErrors.password[0]}</p>}
        </div>

        <button type="submit" className={styles.submit} disabled={isSubmitting}>
          {isSubmitting ? "Logging in…" : "Log in"}
        </button>
      </form>
    </div>
  );
}
