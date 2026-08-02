"use client";

import { useEffect } from "react";
import { useRouter } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth-context";
import { hasAnyRole } from "@/lib/roles";
import type { Role } from "@/types";

/**
 * Renders children only for a signed-in user holding one of `roles`.
 * Anyone else is sent away: to /login when signed out, to the dashboard when signed in
 * without the role — no separate 403 screen, consistent with RequireAuth.
 *
 * This is UX ONLY (ADR-6/ADR-12). The real guard is the backend policy; bypassing this
 * reaches an empty shell whose API calls answer 403.
 *
 * It covers the signed-out case itself rather than requiring a RequireAuth wrapper, so a
 * page cannot be left half-guarded by using only one of the two.
 */
export function RequireRole({
  roles,
  children,
}: {
  roles: readonly Role[];
  children: React.ReactNode;
}) {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const allowed = hasAnyRole(user, roles);

  useEffect(() => {
    if (isLoading) return;

    if (!user) {
      router.push("/login");
      return;
    }

    if (!allowed) {
      router.push("/");
    }
  }, [user, isLoading, allowed, router]);

  if (isLoading || !allowed) {
    return null;
  }

  return <>{children}</>;
}
