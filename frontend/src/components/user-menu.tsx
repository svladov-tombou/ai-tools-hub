"use client";

import { useLocale, useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth-context";
import { formatRoles } from "@/lib/format-roles";

export function UserMenu() {
  const t = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const { user, isLoading, logout } = useAuth();

  if (isLoading) {
    return null;
  }

  if (!user) {
    return (
      <Link
        href="/login"
        className="rounded-md border border-border px-3 py-1.5 text-sm text-text-primary hover:bg-surface"
      >
        {t("auth.loginTitle")}
      </Link>
    );
  }

  const roles = formatRoles(user.roles, locale, (role) => t(`roles.${role}`));

  async function handleLogout() {
    await logout();
    router.push("/login");
  }

  return (
    <div className="flex items-center gap-3">
      <span className="text-sm text-text-secondary">
        {t("nav.placeholderUser", {
          name: user.name,
          roles,
        })}
      </span>
      <button
        type="button"
        onClick={handleLogout}
        className="rounded-md border border-border px-3 py-1.5 text-sm text-text-primary hover:bg-surface"
      >
        {t("nav.logout")}
      </button>
    </div>
  );
}
