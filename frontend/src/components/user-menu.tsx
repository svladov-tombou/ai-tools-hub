"use client";

import { useTranslations } from "next-intl";

// Placeholder user until real Sanctum auth is wired up (separate future phase).
const PLACEHOLDER_USER_NAME = "Иван";

export function UserMenu() {
  const t = useTranslations("common");

  return (
    <div className="flex items-center gap-3">
      <span className="text-sm text-text-secondary">
        {t("nav.placeholderUser", {
          name: PLACEHOLDER_USER_NAME,
          role: t("nav.roleOwner"),
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
