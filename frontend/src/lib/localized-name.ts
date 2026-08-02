import type { LocalizedName } from "@/types";

/**
 * Picks the value for the given locale, falling back to Bulgarian.
 *
 * Category names are translated in the database (ADR-27), so the API returns the whole
 * map and the choice happens here — the backend never learns which language the reader
 * is using. A locale with no translation yet falls back to `bg` rather than rendering
 * an empty label.
 */
export function localizedName(name: LocalizedName, locale: string): string {
  return name[locale as keyof LocalizedName] ?? name.bg;
}

/**
 * Sorts a copy alphabetically by the name the reader actually sees.
 *
 * The backend cannot do this: it orders by slug because it does not know the locale.
 */
export function sortByLocalizedName<T extends { name: LocalizedName }>(
  items: T[],
  locale: string,
): T[] {
  return [...items].sort((a, b) =>
    localizedName(a.name, locale).localeCompare(
      localizedName(b.name, locale),
      locale,
    ),
  );
}
