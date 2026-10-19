import { useState, type FormEvent } from "react";
import { environment } from "../../../environments/environment";
import type { ApiResponse, FieldErrors } from "../../../types/api";
import type { AuthData } from "../types"; // FIX 8: register returns the same data as login
import styles from "./RegisterForm.module.scss";

export default function RegisterForm() {
  // 1. STATE
  //    Form values:
  const [name, setName] = useState(""); // FIX 1: the API requires a name
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordRepeat, setPasswordRepeat] = useState("");

  //    Request status:
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [userName, setUserName] = useState(""); // FIX 8: filled in once registered

  // 2. EVENT HANDLERS
  // FIX 4: renamed register -> handleSubmit (convention: event handlers are handleSomething)
  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();

    setIsSubmitting(true);
    setError("");
    setFieldErrors({});

    try {
      // FIX 5: the options object is the SECOND argument, inside fetch( ... )
      //        before: fetch(url);{ ... }   after: fetch(url, { ... })
      const response = await fetch(`${environment.apiUrl}/users/register`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        // FIX 6: send every field the API needs. The key must be password_confirmation
        //        (Laravel's "confirmed" rule), even though our state is called passwordRepeat.
        body: JSON.stringify({
          name,
          email,
          password,
          password_confirmation: passwordRepeat,
        }),
      });
      const body: ApiResponse<unknown> = await response.json();

      // FIX 7: handle each kind of response, same as LoginForm
      if (body.status.code === 1) {
        const data = body.data as AuthData;
        setUserName(data.user.name);
      } else if (body.status.code === 3) {
        setFieldErrors(body.data as FieldErrors);
      } else {
        setError(body.status.message);
      }
    } catch {
      // FIX 7: every try needs a catch and/or finally
      setError("Could not reach the server. Is the API running?");
    } finally {
      setIsSubmitting(false);
    }
  }

  // 3. EARLY RETURN (FIX 8)
  if (userName) {
    return (
      <div className={styles.page}>
        <p className={styles.success}>Registered and logged in as {userName}</p>
      </div>
    );
  }

  // 4. JSX
  return (
    <div className={styles.page}>
      <form className={styles.card} onSubmit={handleSubmit} noValidate>
        <div className={styles.header}>
          <h1 className={styles.title}>Create an account</h1>
          <p className={styles.subtitle}>Start tracking where your money goes.</p>
        </div>

        {error && (
          <p role="alert" className={styles.alert}>
            {error}
          </p>
        )}

        {/* FIX 1: name input */}
        <div className={styles.field}>
          <label htmlFor="name">Name</label>
          <input
            id="name"
            autoComplete="name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            aria-invalid={!!fieldErrors.name}
          />
          {fieldErrors.name && <p className={styles.fieldError}>{fieldErrors.name[0]}</p>}
        </div>

        <div className={styles.field}>
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            aria-invalid={!!fieldErrors.email}
          />
          {fieldErrors.email && <p className={styles.fieldError}>{fieldErrors.email[0]}</p>}
        </div>

        <div className={styles.field}>
          <label htmlFor="password">Password</label>
          <input
            id="password"
            type="password"
            autoComplete="new-password" // FIX: "new-password" on sign-up, "current-password" on login
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            aria-invalid={!!fieldErrors.password}
          />
          {/* Laravel puts the "confirmation does not match" message under password too */}
          {fieldErrors.password && <p className={styles.fieldError}>{fieldErrors.password[0]}</p>}
        </div>

        {/* FIX 8: the repeat-password input was missing */}
        <div className={styles.field}>
          <label htmlFor="passwordRepeat">Repeat password</label>
          <input
            id="passwordRepeat"
            type="password"
            autoComplete="new-password"
            value={passwordRepeat}
            onChange={(e) => setPasswordRepeat(e.target.value)}
          />
        </div>

        {/* FIX: button text said "Log in" (copied from LoginForm) */}
        <button type="submit" className={styles.submit} disabled={isSubmitting}>
          {isSubmitting ? "Creating account…" : "Create account"}
        </button>
      </form>
    </div>
  );
}
