"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import type { UpdateUserPayload } from "@/lib/api";
import type { AdminUser, Department } from "@/types";

const INPUT_CLASS =
  "rounded-md border border-border bg-surface px-3 py-2 text-text-primary outline-none focus:border-accent";

/**
 * Reads its initial values once, in a useState initializer — the parent (UserEditPanel via
 * UsersAdmin) must remount this whole subtree with a `key` tied to the user id when
 * switching rows, or it would keep showing the previous user's values (the ADR-24 trap).
 */
export function UserDetailsForm({
  user,
  departments,
  isSubmitting,
  formError,
  fieldErrors,
  onSubmit,
}: {
  user: AdminUser;
  departments: Department[];
  isSubmitting: boolean;
  formError: string | null;
  fieldErrors: Record<string, string[]>;
  onSubmit: (payload: UpdateUserPayload) => void;
}) {
  const t = useTranslations("common");

  const [name, setName] = useState(() => user.name);
  const [email, setEmail] = useState(() => user.email);
  const [departmentId, setDepartmentId] = useState<number | null>(() => user.department_id);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    // department_id is always sent explicitly (never omitted): an omitted value clears the
    // stored department on the backend, so "no department" must be sent as `null`.
    onSubmit({ name, email, department_id: departmentId });
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <h3 className="text-sm font-semibold text-text-primary">
        {t("settings.users.detailsSection")}
      </h3>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={`user-${user.id}-name`} className="text-sm text-text-secondary">
          {t("settings.users.nameLabel")}
        </label>
        <input
          id={`user-${user.id}-name`}
          type="text"
          value={name}
          onChange={(event) => setName(event.target.value)}
          required
          maxLength={255}
          className={INPUT_CLASS}
        />
        {fieldErrors.name && <p className="text-sm text-error">{fieldErrors.name[0]}</p>}
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={`user-${user.id}-email`} className="text-sm text-text-secondary">
          {t("settings.users.emailLabel")}
        </label>
        <input
          id={`user-${user.id}-email`}
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          maxLength={255}
          className={INPUT_CLASS}
        />
        {fieldErrors.email && <p className="text-sm text-error">{fieldErrors.email[0]}</p>}
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={`user-${user.id}-department`} className="text-sm text-text-secondary">
          {t("settings.users.departmentLabel")}
        </label>
        <select
          id={`user-${user.id}-department`}
          value={departmentId ?? ""}
          onChange={(event) =>
            setDepartmentId(event.target.value === "" ? null : Number(event.target.value))
          }
          className={INPUT_CLASS}
        >
          <option value="">{t("settings.users.departmentNone")}</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {t(`departments.${department.slug}`)}
            </option>
          ))}
        </select>
        {fieldErrors.department_id && (
          <p className="text-sm text-error">{fieldErrors.department_id[0]}</p>
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
