"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ThemeToggle } from "@/components/theme-toggle";
import { LanguageSwitcher } from "@/components/language-switcher";
import { UserMenu } from "@/components/user-menu";
import { useAuth } from "@/lib/auth-context";
import { NAV_LINKS } from "@/lib/nav-links";
import { hasAnyRole } from "@/lib/roles";

function NavLinksList({
  className,
  onNavigate,
}: {
  className?: string;
  onNavigate?: () => void;
}) {
  const t = useTranslations("common");
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return null;
  }

  if (!user) {
    return null;
  }

  return (
    <nav className={className}>
      {NAV_LINKS.filter(
        (link) => !link.requiredRoles || hasAnyRole(user, link.requiredRoles),
      ).map((link) => (
        <Link
          key={link.href}
          href={link.href}
          onClick={onNavigate}
          className="text-sm text-text-secondary hover:text-text-primary"
        >
          {t(link.labelKey)}
        </Link>
      ))}
    </nav>
  );
}

export function Navbar() {
  const t = useTranslations("common");
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  return (
    <header className="border-b border-border bg-card">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
        <div className="flex items-center gap-8">
          <Link href="/" className="text-lg font-semibold text-text-primary">
            {t("app.title")}
          </Link>
          <NavLinksList className="hidden items-center gap-6 md:flex" />
        </div>

        <div className="hidden items-center gap-4 md:flex">
          <LanguageSwitcher />
          <ThemeToggle />
          <UserMenu />
        </div>

        <button
          type="button"
          onClick={() => setIsMenuOpen((open) => !open)}
          aria-label={isMenuOpen ? t("nav.closeMenu") : t("nav.openMenu")}
          aria-expanded={isMenuOpen}
          className="flex size-9 items-center justify-center rounded-md border border-border bg-card text-text-primary md:hidden"
        >
          {isMenuOpen ? (
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth={2}
              className="size-5"
            >
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          ) : (
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth={2}
              className="size-5"
            >
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          )}
        </button>
      </div>

      {isMenuOpen && (
        <div className="flex flex-col gap-4 border-t border-border px-4 py-4 md:hidden">
          <NavLinksList
            className="flex flex-col gap-3"
            onNavigate={() => setIsMenuOpen(false)}
          />
          <div className="flex items-center gap-4">
            <LanguageSwitcher />
            <ThemeToggle />
          </div>
          <UserMenu />
        </div>
      )}
    </header>
  );
}
