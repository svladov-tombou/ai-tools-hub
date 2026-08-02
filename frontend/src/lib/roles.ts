import type { User } from "@/lib/api";
import type { AdminUser, Role } from "@/types";

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

/**
 * The three checks below mirror `UserPolicy` on the backend (owner-protection and
 * self-service rules). They are UX ONLY: the real guard is the backend policy, and a user
 * who bypasses a disabled button here still gets a 403 from the API.
 */

/** Whether `target` holds the `owner` role at all. */
export function isOwnerUser(target: AdminUser): boolean {
  return target.roles.some((role) => role.name === "owner");
}

/**
 * Mirrors `UserPolicy::update`/`activate`/`deactivate`: an actor holding `owner` may
 * manage anyone; anyone else (a pm) may not manage a user who holds the `owner` role.
 */
export function canManageUser(actor: User | null, target: AdminUser): boolean {
  if (!actor) return false;
  if (actor.roles.includes("owner")) return true;
  return !isOwnerUser(target);
}

/** Mirrors `UserPolicy`: nobody may edit their own roles, even an owner. */
export function canEditUserRoles(actor: User | null, target: AdminUser): boolean {
  if (!actor) return false;
  return canManageUser(actor, target) && actor.id !== target.id;
}

/** Mirrors `UserPolicy`: nobody may deactivate their own account. */
export function canDeactivateUser(actor: User | null, target: AdminUser): boolean {
  if (!actor) return false;
  return canManageUser(actor, target) && actor.id !== target.id;
}
