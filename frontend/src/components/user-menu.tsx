"use client";

import { useLocale, useTranslations } from "next-intl";
import { PLACEHOLDER_USER } from "@/lib/placeholder-user";
import { formatRoles } from "@/lib/format-roles";

export function UserMenu() {
  const t = useTranslations("common");
  const locale = useLocale();
  const roles = formatRoles(PLACEHOLDER_USER.roles, locale, (role) =>
    t(`roles.${role}`),
  );

  return (
    <div className="flex items-center gap-3">
      <span className="text-sm text-text-secondary">
        {t("nav.placeholderUser", {
          name: PLACEHOLDER_USER.name,
          roles,
        })}
      </span>
      <button
        type="button"
        className="rounded-md border border-border px-3 py-1.5 text-sm text-text-primary hover:bg-surface"
      >
        {t("nav.logout")}
      </button>
    </div>
  );
}
