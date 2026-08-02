"use client";

import { useLocale, useTranslations } from "next-intl";
import { localizedName } from "@/lib/localized-name";
import type { CategoryWithUsage } from "@/types";

export function CategoryRow({
  category,
  onEdit,
  onDelete,
}: {
  category: CategoryWithUsage;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const t = useTranslations("common");
  const locale = useLocale();

  // A category in use cannot be deleted (ADR-28). The count comes from the backend
  // (ADR-30), so the button is disabled before the click rather than after a failed one.
  const isUsed = category.tools_count > 0;

  return (
    <li className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4">
      <div className="flex flex-col gap-0.5">
        <span className="font-medium text-text-primary">
          {localizedName(category.name, locale)}
        </span>
        <span className="text-sm text-text-secondary">{category.slug}</span>
        <span className="text-sm text-text-secondary">
          {isUsed
            ? t("settings.categories.usedBy", { count: category.tools_count })
            : t("settings.categories.unused")}
        </span>
      </div>

      <div className="flex shrink-0 items-center gap-2">
        <button
          type="button"
          onClick={onEdit}
          className="rounded-md border border-border px-3 py-1.5 text-sm text-text-primary hover:bg-surface"
        >
          {t("settings.categories.editButton")}
        </button>
        <button
          type="button"
          onClick={onDelete}
          disabled={isUsed}
          title={isUsed ? t("settings.categories.deleteBlocked") : undefined}
          className="rounded-md border border-border px-3 py-1.5 text-sm text-error hover:bg-surface disabled:cursor-not-allowed disabled:opacity-50"
        >
          {t("settings.categories.deleteButton")}
        </button>
      </div>
    </li>
  );
}
