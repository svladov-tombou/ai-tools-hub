import type { User } from "@/lib/api";
import type { Role } from "@/types";

/**
 * owner and pm are the platform administrators: they moderate every tool (ToolPolicy,
 * ADR-12) and manage categories (CategoryPolicy, ADR-28).
 *
 * Every check built on this is UX ONLY. The security boundary is the backend policy;
 * a user who reaches a hidden screen still gets 403 from the API and sees no data.
 */
export const ADMIN_ROLES: readonly Role[] = ["owner", "pm"];

export function hasAnyRole(user: User | null, roles: readonly Role[]): boolean {
  return Boolean(user && roles.some((role) => user.roles.includes(role)));
}
