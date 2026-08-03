"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { getDepartments } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import { formatRoles } from "@/lib/format-roles";
import type { Department } from "@/types";

/**
 * The signed-in user's own data, READ-ONLY as plain text rather than disabled inputs
 * (ADR-40(9)): these are facts about the account, not fields, and a greyed-out input
 * invites someone to wire it up.
 */
export function ProfileView() {
  const { user } = useAuth();
  const t = useTranslations("common");
  const locale = useLocale();

  // null means "not loaded yet", which is NOT the same as "loaded and empty" — see the
  // department row below. Fetched here because there is no parent orchestrator on this
  // screen; users-admin.tsx does the same for user-row.tsx.
  const [departments, setDepartments] = useState<Department[] | null>(null);

  useEffect(() => {
    let isMounted = true;

    getDepartments()
      .then((loaded) => {
        if (isMounted) setDepartments(loaded);
      })
      .catch(() => {
        // Leaves the value blank rather than claiming the user has no department.
      });

    return () => {
      isMounted = false;
    };
  }, []);

  // RequireAuth renders children only for a signed-in user; this satisfies the type and
  // mirrors dashboard-greeting.tsx.
  if (!user) {
    return null;
  }

  const department = departments?.find((candidate) => candidate.id === user.department_id);

  // While `departments` is null the value stays empty on purpose: rendering the "no
  // department" text before the list arrives would state something false about a user who
  // does have one, and this project has no loading string for this screen.
  const departmentLabel = departments
    ? department
      ? t(`departments.${department.slug}`)
      : t("profile.noDepartment")
    : "";

  const rows = [
    { label: t("profile.name"), value: user.name },
    { label: t("profile.email"), value: user.email },
    { label: t("profile.roles"), value: formatRoles(user.roles, locale, (role) => t(`roles.${role}`)) },
    { label: t("profile.department"), value: departmentLabel },
  ];

  return (
    <dl className="flex flex-col gap-4 rounded-lg border border-border bg-card p-6">
      {rows.map((row) => (
        <div key={row.label} className="flex flex-col gap-0.5">
          <dt className="text-sm text-text-secondary">{row.label}</dt>
          <dd className="text-text-primary">{row.value}</dd>
        </div>
      ))}
    </dl>
  );
}
