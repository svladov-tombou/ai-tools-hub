"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { updateCurrentUserPassword, ValidationError } from "@/lib/api";

const INPUT_CLASS =
  "rounded-md border border-border bg-surface px-3 py-2 text-text-primary outline-none focus:border-accent";

type PasswordValues = { current: string; next: string; confirmation: string };

const EMPTY_VALUES: PasswordValues = { current: "", next: "", confirmation: "" };

/**
 * Self-service password change (ADR-40(7)). A NEW component rather than a reuse of
 * user-password-form.tsx, whose input ids are fixed, whose strings are `settings.users.*`
 * and which has no `current_password` field.
 *
 * Unlike that one this is NOT a dumb child: `/profile/page.tsx` is a server component, so
 * there is no client orchestrator to hold the submit state or the remount `key` ADR-40(7)
 * describes. It therefore owns its state and clears its own fields on success, which is the
 * same guarantee by a different means.
 */
export function ProfilePasswordForm() {
  const t = useTranslations("common");

  const [values, setValues] = useState<PasswordValues>(EMPTY_VALUES);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [notice, setNotice] = useState<string | null>(null);

  function set(field: keyof PasswordValues, value: string) {
    setValues((current) => ({ ...current, [field]: value }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setFormError(null);
    setFieldErrors({});
    setNotice(null);

    try {
      await updateCurrentUserPassword({
        current_password: values.current,
        password: values.next,
        password_confirmation: values.confirmation,
      });
      setValues(EMPTY_VALUES);
      setNotice(t("profile.password.success"));
    } catch (err) {
      // The mapping from users-admin.tsx:94-103: per-field messages come straight from the
      // API, the form-level line is ours.
      if (err instanceof ValidationError) {
        setFormError(t("profile.password.validationFailed"));
        setFieldErrors(err.errors);
      } else {
        setFormError(t("profile.password.genericError"));
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  // errorKey is the BACKEND field name: the API's own message is what renders under the
  // input, exactly as in every other form in this project.
  // `minLength` is deliberately absent on the current password: it is matched against
  // whatever is stored, and a browser-side minimum would block a correct value for anyone
  // whose password predates the 8-character rule. The new password is where the rule lives.
  // This is the FIRST form in the project to carry autoComplete; the others (the two fields
  // in settings/users, and the login form) get it separately — see ADR-40.
  const fields = [
    {
      key: "current" as const,
      label: t("profile.password.current"),
      errorKey: "current_password",
      autoComplete: "current-password",
    },
    {
      key: "next" as const,
      label: t("profile.password.new"),
      errorKey: "password",
      minLength: 8,
      autoComplete: "new-password",
    },
    {
      key: "confirmation" as const,
      label: t("profile.password.confirm"),
      errorKey: "password_confirmation",
      minLength: 8,
      autoComplete: "new-password",
    },
  ];

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-col gap-4 rounded-lg border border-border bg-card p-6"
    >
      <h2 className="text-lg font-medium text-text-primary">{t("profile.password.title")}</h2>

      {fields.map((field) => (
        <div key={field.key} className="flex flex-col gap-1.5">
          <label htmlFor={`profile-password-${field.key}`} className="text-sm text-text-secondary">
            {field.label}
          </label>
          <input
            id={`profile-password-${field.key}`}
            type="password"
            value={values[field.key]}
            onChange={(event) => set(field.key, event.target.value)}
            required
            minLength={field.minLength}
            autoComplete={field.autoComplete}
            className={INPUT_CLASS}
          />
          {fieldErrors[field.errorKey] && (
            <p className="text-sm text-error">{fieldErrors[field.errorKey][0]}</p>
          )}
        </div>
      ))}

      {formError && <p className="text-sm text-error">{formError}</p>}
      {notice && <p className="text-sm text-accent">{notice}</p>}

      <div>
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-foreground disabled:opacity-60"
        >
          {t("profile.password.submit")}
        </button>
      </div>
    </form>
  );
}
