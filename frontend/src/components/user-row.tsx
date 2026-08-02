"use client";

import { useTranslations } from "next-intl";
import type { User } from "@/lib/api";
import { canDeactivateUser, canManageUser } from "@/lib/roles";
import type { AdminUser, Department } from "@/types";

export function UserRow({
  user,
  actor,
  departments,
  onEdit,
  onActivate,
  onDeactivate,
}: {
  user: AdminUser;
  actor: User | null;
  departments: Department[];
  onEdit: () => void;
  onActivate: () => void;
  onDeactivate: () => void;
}) {
  const t = useTranslations("common");

  const department = departments.find((candidate) => candidate.id === user.department_id) ?? null;
  const isSelf = actor?.id === user.id;

  // Mirrors UserPolicy (owner-protection and self-service rules). UX only — the backend
  // policy is the real guard, and a bypassed check here reaches a 403.
  const canManage = canManageUser(actor, user);
  const canDeactivate = canDeactivateUser(actor, user);
  const deactivateHint = isSelf
    ? t("settings.users.selfDeactivateHint")
    : t("settings.users.ownerProtectedHint");

  return (
    <li className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4">
      <div className="flex flex-col gap-0.5">
        <span className="font-medium text-text-primary">{user.name}</span>
        <span className="text-sm text-text-secondary">{user.email}</span>
        <span className="text-sm text-text-secondary">
          {user.roles.map((role) => t(`roles.${role.name}`)).join(", ")}
          {" · "}
          {department ? t(`departments.${department.slug}`) : t("settings.users.departmentNone")}
        </span>
      </div>

      <div className="flex shrink-0 items-center gap-2">
        {user.is_active ? (
          <span className="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">
            {t("settings.users.activeBadge")}
          </span>
        ) : (
          <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
            {t("settings.users.inactiveBadge")}
          </span>
        )}

        <button
          type="button"
          onClick={onEdit}
          disabled={!canManage}
          title={!canManage ? t("settings.users.ownerProtectedHint") : undefined}
          className="rounded-md border border-border px-3 py-1.5 text-sm text-text-primary hover:bg-surface disabled:cursor-not-allowed disabled:opacity-50"
        >
          {t("settings.users.editButton")}
        </button>

        {user.is_active ? (
          <button
            type="button"
            onClick={onDeactivate}
            disabled={!canDeactivate}
            title={!canDeactivate ? deactivateHint : undefined}
            className="rounded-md border border-border px-3 py-1.5 text-sm text-error hover:bg-surface disabled:cursor-not-allowed disabled:opacity-50"
          >
            {t("settings.users.deactivateButton")}
          </button>
        ) : (
          <button
            type="button"
            onClick={onActivate}
            disabled={!canManage}
            title={!canManage ? t("settings.users.ownerProtectedHint") : undefined}
            className="rounded-md border border-border px-3 py-1.5 text-sm text-text-primary hover:bg-surface disabled:cursor-not-allowed disabled:opacity-50"
          >
            {t("settings.users.activateButton")}
          </button>
        )}
      </div>
    </li>
  );
}
