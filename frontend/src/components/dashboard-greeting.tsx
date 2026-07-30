"use client";

import { useLocale, useTranslations } from "next-intl";
import { useAuth } from "@/lib/auth-context";
import { formatRoles } from "@/lib/format-roles";

export function DashboardGreeting() {
  const { user } = useAuth();
  const t = useTranslations("common");
  const locale = useLocale();

  if (!user) {
    return null;
  }

  const roles = formatRoles(user.roles, locale, (role) => t(`roles.${role}`));

  return (
    <div className="flex flex-col gap-1">
      <h1 className="text-2xl font-semibold text-text-primary">
        {t("dashboard.greeting", { name: user.name })}
      </h1>
      <p className="text-text-secondary">
        {t("dashboard.rolesLabel", { roles })}
      </p>
    </div>
  );
}
