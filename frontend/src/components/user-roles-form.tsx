"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import type { UpdateUserRolesPayload, User } from "@/lib/api";
import { CheckboxGroup } from "@/components/checkbox-group";
import type { AdminUser, RoleOption } from "@/types";

/**
 * Only rendered by UserEditPanel when `canEditUserRoles` is true, so `user` here is never
 * the signed-in actor's own row. Reads its initial selection once, in a useState
 * initializer — same remount contract as UserDetailsForm.
 */
export function UserRolesForm({
  user,
  actor,
  roles,
  isSubmitting,
  formError,
  fieldErrors,
  onSubmit,
}: {
  user: AdminUser;
  actor: User | null;
  roles: RoleOption[];
  isSubmitting: boolean;
  formError: string | null;
  fieldErrors: Record<string, string[]>;
  onSubmit: (payload: UpdateUserRolesPayload) => void;
}) {
  const t = useTranslations("common");

  const [roleIds, setRoleIds] = useState<number[]>(() => user.roles.map((role) => role.id));

  // A pm can never legitimately grant the owner role (UserPolicy), so the option is
  // OMITTED rather than disabled — a disabled checkbox would still hold a value.
  const isOwnerActor = Boolean(actor?.roles.includes("owner"));
  const availableRoles = isOwnerActor ? roles : roles.filter((role) => role.name !== "owner");

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit({ role_ids: roleIds });
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <h3 className="text-sm font-semibold text-text-primary">
        {t("settings.users.rolesSection")}
      </h3>

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
