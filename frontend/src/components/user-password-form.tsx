"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import type { UpdateUserPasswordPayload } from "@/lib/api";

const INPUT_CLASS =
  "rounded-md border border-border bg-surface px-3 py-2 text-text-primary outline-none focus:border-accent";

/**
 * Starts empty (no user data to seed) and is expected to clear itself after a successful
 * save. It does not track that itself — the parent remounts it with a fresh `key` on
 * success, the same trick as the ADR-24 remount but used here to force a reset rather
 * than to prevent one.
 */
export function UserPasswordForm({
  isSubmitting,
  formError,
  fieldErrors,
  onSubmit,
}: {
  isSubmitting: boolean;
  formError: string | null;
  fieldErrors: Record<string, string[]>;
  onSubmit: (payload: UpdateUserPasswordPayload) => void;
}) {
  const t = useTranslations("common");

  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit({ password, password_confirmation: passwordConfirmation });
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <h3 className="text-sm font-semibold text-text-primary">
        {t("settings.users.passwordSection")}
      </h3>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="user-password" className="text-sm text-text-secondary">
          {t("settings.users.passwordLabel")}
        </label>
        <input
          id="user-password"
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          required
          minLength={8}
          className={INPUT_CLASS}
        />
        <p className="text-sm text-text-secondary">{t("settings.users.passwordHint")}</p>
        {fieldErrors.password && <p className="text-sm text-error">{fieldErrors.password[0]}</p>}
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="user-password-confirmation" className="text-sm text-text-secondary">
          {t("settings.users.passwordConfirmLabel")}
        </label>
        <input
          id="user-password-confirmation"
          type="password"
          value={passwordConfirmation}
          onChange={(event) => setPasswordConfirmation(event.target.value)}
          required
          minLength={8}
          className={INPUT_CLASS}
        />
        {fieldErrors.password_confirmation && (
          <p className="text-sm text-error">{fieldErrors.password_confirmation[0]}</p>
        )}
      </div>

      {formError && <p className="text-sm text-error">{formError}</p>}

      <div>
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-foreground disabled:opacity-60"
        >
          {t("buttons.save")}
        </button>
      </div>
    </form>
  );
}
