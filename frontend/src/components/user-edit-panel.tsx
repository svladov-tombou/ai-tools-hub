"use client";

import { useTranslations } from "next-intl";
import type {
  UpdateUserPasswordPayload,
  UpdateUserPayload,
  UpdateUserRolesPayload,
  User,
} from "@/lib/api";
import { canEditUserRoles } from "@/lib/roles";
import { UserDetailsForm } from "@/components/user-details-form";
import { UserPasswordForm } from "@/components/user-password-form";
import { UserRolesForm } from "@/components/user-roles-form";
import type { AdminUser, Department, RoleOption } from "@/types";

/** Submitting/error/fieldErrors for one of the three independent save blocks below. */
export type BlockState = {
  isSubmitting: boolean;
  error: string | null;
  fieldErrors: Record<string, string[]>;
};

/**
 * One card, three independently-submitting forms — details, roles, password — each with
 * its own state so one block's error never appears under another. The parent (UsersAdmin)
 * owns all of that state and must give this component a `key` tied to the user id: the
 * child forms read their initial values once, in a useState initializer, so without a
 * remount, switching to another user's row would keep showing the previous one's values
 * (the ADR-24 trap).
 */
export function UserEditPanel({
  user,
  actor,
  roles,
  departments,
  detailsState,
  rolesState,
  passwordState,
  passwordFormVersion,
  onSaveDetails,
  onSaveRoles,
  onSavePassword,
  onClose,
}: {
  user: AdminUser;
  actor: User | null;
  roles: RoleOption[];
  departments: Department[];
  detailsState: BlockState;
  rolesState: BlockState;
  passwordState: BlockState;
  passwordFormVersion: number;
  onSaveDetails: (payload: UpdateUserPayload) => void;
  onSaveRoles: (payload: UpdateUserRolesPayload) => void;
  onSavePassword: (payload: UpdateUserPasswordPayload) => void;
  onClose: () => void;
}) {
  const t = useTranslations("common");

  return (
    <div className="flex flex-col gap-6 rounded-lg border border-border bg-card p-6">
      <h2 className="text-lg font-medium text-text-primary">
        {t("settings.users.editTitle", { name: user.name })}
      </h2>

      <UserDetailsForm
        user={user}
        departments={departments}
        isSubmitting={detailsState.isSubmitting}
        formError={detailsState.error}
        fieldErrors={detailsState.fieldErrors}
        onSubmit={onSaveDetails}
      />

      <div className="border-t border-border pt-4">
        {canEditUserRoles(actor, user) ? (
          <UserRolesForm
            user={user}
            actor={actor}
            roles={roles}
            isSubmitting={rolesState.isSubmitting}
            formError={rolesState.error}
            fieldErrors={rolesState.fieldErrors}
            onSubmit={onSaveRoles}
          />
        ) : (
          <p className="text-sm text-text-secondary">{t("settings.users.selfRolesNotice")}</p>
        )}
      </div>

      <div className="border-t border-border pt-4">
        <UserPasswordForm
          key={passwordFormVersion}
          isSubmitting={passwordState.isSubmitting}
          formError={passwordState.error}
          fieldErrors={passwordState.fieldErrors}
          onSubmit={onSavePassword}
        />
      </div>

      <div className="border-t border-border pt-4">
        <button
          type="button"
          onClick={onClose}
          className="rounded-md border border-border px-4 py-2 text-sm text-text-primary hover:bg-surface"
        >
          {t("settings.users.closeButton")}
        </button>
      </div>
    </div>
  );
}
