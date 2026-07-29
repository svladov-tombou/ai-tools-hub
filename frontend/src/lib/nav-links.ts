import type { Role } from "@/types";

export type NavLink = {
  href: string;
  labelKey: "nav.dashboard" | "nav.tools" | "nav.profile";
  requiredRole?: Role;
};

export const NAV_LINKS: NavLink[] = [
  { href: "/", labelKey: "nav.dashboard" },
  { href: "/tools", labelKey: "nav.tools" },
  { href: "/profile", labelKey: "nav.profile" },
];
