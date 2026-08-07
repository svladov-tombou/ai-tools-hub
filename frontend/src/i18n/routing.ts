import { defineRouting } from "next-intl/routing";

export const routing = defineRouting({
  locales: ["bg", "en", "fr"],
  defaultLocale: "bg",
  localeDetection: false,
});

export type Locale = (typeof routing.locales)[number];
