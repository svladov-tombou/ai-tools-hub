"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import type { CreateUserPayload, User } from "@/lib/api";
import { CheckboxGroup } from "@/components/checkbox-group";
import type { Department, RoleOption } from "@/types";

const INPUT_CLASS =
  "rounded-md border border-border bg-surface px-3 py-2 text-text-primary outline-none focus:border-accent";

/**
 * Props: `roles`/`departments` are the option lists, `actor` is the signed-in user (used
 * only to decide whether the `owner` role option may be offered at all).
 */
export function UserCreateForm({
  roles,
  departments,
  actor,
  isSubmitting,
  formError,
  fieldErrors,
  onSubmit,
  onCancel,
}: {
  roles: RoleOption[];
  departments: Department[];
  actor: User | null;
  isSubmitting: boolean;
  formError: string | null;
  fieldErrors: Record<string, string[]>;
  onSubmit: (payload: CreateUserPayload) => void;
  onCancel: () => void;
}) {
  const t = useTranslations("common");

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [roleIds, setRoleIds] = useState<number[]>([]);
  const [departmentId, setDepartmentId] = useState<number | null>(null);

  // A pm can never legitimately grant the owner role (UserPolicy), so the option is
  // OMITTED rather than disabled — a disabled checkbox would still hold a value.
  const isOwnerActor = Boolean(actor?.roles.includes("owner"));
  const availableRoles = isOwnerActor ? roles : roles.filter((role) => role.name !== "owner");

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit({
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
      role_ids: roleIds,
      department_id: departmentId,
    });
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-col gap-4 rounded-lg border border-border bg-card p-6"
    >
      <h2 className="text-lg font-medium text-text-primary">{t("settings.users.newTitle")}</h2>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="new-user-name" className="text-sm text-text-secondary">
          {t("settings.users.nameLabel")}
        </label>
        <input
          id="new-user-name"
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
        <label htmlFor="new-user-email" className="text-sm text-text-secondary">
          {t("settings.users.emailLabel")}
        </label>
        <input
          id="new-user-email"
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
        <label htmlFor="new-user-password" className="text-sm text-text-secondary">
          {t("settings.users.passwordLabel")}
        </label>
        <input
          id="new-user-password"
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
        <label htmlFor="new-user-password-confirmation" className="text-sm text-text-secondary">
          {t("settings.users.passwordConfirmLabel")}
        </label>
        <input
          id="new-user-password-confirmation"
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

      <CheckboxGroup
        heading={t("settings.users.rolesLabel")}
        columns={2}
        hint={isOwnerActor ? undefined : t("settings.users.ownerRoleHint")}
        options={availableRoles.map((role) => ({ id: role.id, label: t(`roles.${role.name}`) }))}
        selectedIds={roleIds}
        onChange={setRoleIds}
        disabled={isSubmitting}
      />
      {fieldErrors.role_ids && <p className="text-sm text-error">{fieldErrors.role_ids[0]}</p>}

      <div className="flex flex-col gap-1.5">
        <label htmlFor="new-user-department" className="text-sm text-text-secondary">
          {t("settings.users.departmentLabel")}
        </label>
        <select
          id="new-user-department"
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

      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-md bg-accent px-4 py-2 font-medium text-accent-foreground disabled:opacity-60"
        >
          {t("buttons.save")}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-md border border-border px-4 py-2 text-text-primary hover:bg-surface"
        >
          {t("buttons.cancel")}
        </button>
      </div>
    </form>
  );
}
