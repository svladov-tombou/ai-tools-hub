"use client";

import { useTranslations } from "next-intl";
import type { User } from "@/lib/api";
import { UserRow } from "@/components/user-row";
import type { AdminUser, Department } from "@/types";

/**
 * Pulled out of UsersAdmin so that file stays focused on state and handlers rather than
 * also carrying the list markup (the "split the list out" note in the phase spec).
 */
export function UserList({
  users,
  actor,
  departments,
  onEdit,
  onActivate,
  onDeactivate,
}: {
  users: AdminUser[];
  actor: User | null;
  departments: Department[];
  onEdit: (user: AdminUser) => void;
  onActivate: (user: AdminUser) => void;
  onDeactivate: (user: AdminUser) => void;
}) {
  const t = useTranslations("common");

  if (users.length === 0) {
    return <p className="text-text-secondary">{t("settings.users.empty")}</p>;
  }

  return (
    <ul className="flex flex-col gap-3">
      {users.map((user) => (
        <UserRow
          key={user.id}
          user={user}
          actor={actor}
          departments={departments}
          onEdit={() => onEdit(user)}
          onActivate={() => onActivate(user)}
          onDeactivate={() => onDeactivate(user)}
        />
      ))}
    </ul>
  );
}
