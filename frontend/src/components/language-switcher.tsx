"use client";

import { useLocale } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";

export function LanguageSwitcher() {
  const activeLocale = useLocale();
  const pathname = usePathname();

  return (
    <div className="flex items-center gap-1 text-sm">
      {routing.locales.map((locale, index) => (
        <span key={locale} className="flex items-center gap-1">
          {index > 0 && <span className="text-text-secondary">|</span>}
          <Link
            href={pathname}
            locale={locale}
            aria-current={locale === activeLocale ? "true" : undefined}
            className={
              locale === activeLocale
                ? "font-semibold text-text-primary"
                : "text-text-secondary hover:text-text-primary"
            }
          >
            {locale.toUpperCase()}
          </Link>
        </span>
      ))}
    </div>
  );
}
