import { ADMIN_ROLES } from "@/lib/roles";
import type { Role } from "@/types";

export type NavLink = {
  href: string;
  labelKey: "nav.dashboard" | "nav.tools" | "nav.profile" | "nav.settings";
  /**
   * Shown only to a user holding at least one of these roles; omitted means everyone.
   * A list, not a single role, because the admin sections are open to owner OR pm —
   * the same pair the backend policies check.
   */
  requiredRoles?: readonly Role[];
};

export const NAV_LINKS: NavLink[] = [
  { href: "/", labelKey: "nav.dashboard" },
  { href: "/tools", labelKey: "nav.tools" },
  { href: "/profile", labelKey: "nav.profile" },
  { href: "/settings", labelKey: "nav.settings", requiredRoles: ADMIN_ROLES },
];
