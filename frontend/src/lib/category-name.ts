import type { CategoryNamePayload } from "@/lib/api";

/**
 * Builds the translation map to send, dropping languages left blank.
 *
 * The backend rejects a present-but-empty translation (ADR-28), so "" must be OMITTED
 * rather than sent — an empty English name means "not translated yet", which the UI
 * renders by falling back to Bulgarian (ADR-27), not by showing a blank label.
 */
export function toCategoryName(values: {
  bg: string;
  en: string;
  fr: string;
}): CategoryNamePayload {
  const name: CategoryNamePayload = { bg: values.bg.trim() };

  const en = values.en.trim();
  const fr = values.fr.trim();

  if (en) name.en = en;
  if (fr) name.fr = fr;

  return name;
}
