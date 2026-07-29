import type { Role } from "@/types";

export function formatRoles(
  roles: Role[],
  locale: string,
  translateRole: (role: Role) => string,
): string {
  return new Intl.ListFormat(locale, {
    style: "long",
    type: "conjunction",
  }).format(roles.map(translateRole));
}
