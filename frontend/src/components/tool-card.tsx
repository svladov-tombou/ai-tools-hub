"use client";

import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth-context";
import { localizedName } from "@/lib/localized-name";
import { ADMIN_ROLES, hasAnyRole } from "@/lib/roles";
import type { Tool } from "@/types";

export function ToolCard({ tool }: { tool: Tool }) {
  const t = useTranslations("common");
  const locale = useLocale();
  const { user } = useAuth();
  // Mirrors ToolPolicy::update — author, or an administrator. UX only.
  const canEdit = Boolean(
    user && (tool.created_by === user.id || hasAnyRole(user, ADMIN_ROLES)),
  );

  return (
    <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-6">
      <div className="flex items-start justify-between gap-2">
        <h2 className="text-lg font-medium text-text-primary">{tool.name}</h2>
        <div className="flex shrink-0 items-center gap-2">
          {tool.status === "draft" && (
            <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
              {t("tools.draftBadge")}
            </span>
          )}
          {canEdit && (
            <Link
              href={`/tools/${tool.id}/edit`}
              className="rounded-md border border-border px-2 py-0.5 text-xs text-text-primary hover:underline"
            >
              {t("tools.editButton")}
            </Link>
          )}
        </div>
      </div>

      <p className="text-sm text-text-secondary line-clamp-3">{tool.description}</p>

      <div className="flex flex-col gap-2 text-sm">
        {tool.categories.length > 0 && (
          <div>
            <span className="font-semibold text-text-primary">
              {t("tools.categoriesLabel")}:
            </span>
            <p className="text-text-secondary">
              {tool.categories
                .map((category) => localizedName(category.name, locale))
                .join(", ")}
            </p>
          </div>
        )}

        {tool.departments.length > 0 && (
          <div>
            <span className="font-semibold text-text-primary">
              {t("tools.departmentsLabel")}:
            </span>
            <p className="text-text-secondary">
              {tool.departments
                .map((department) => t(`departments.${department.slug}`))
                .join(", ")}
            </p>
          </div>
        )}

        {tool.roles.length > 0 && (
          <div>
            <span className="font-semibold text-text-primary">
              {t("tools.rolesLabel")}:
            </span>
            <p className="text-text-secondary">
              {tool.roles.map((role) => t(`roles.${role.name}`)).join(", ")}
            </p>
          </div>
        )}

        {tool.difficulty && (
          <p>
            <span className="font-semibold text-text-primary">
              {t("tools.difficultyLabel")}:
            </span>{" "}
            <span className="text-text-secondary">
              {t(`tools.difficulty.${tool.difficulty}`)}
            </span>
          </p>
        )}

        {/* Hidden entirely when there is no creator: created_by is nullable with nullOnDelete
            (ADR-11), so a removed user leaves an authorless tool. The card already hides empty
            groups rather than printing a placeholder. */}
        {tool.creator && (
          <p>
            <span className="font-semibold text-text-primary">
              {t("tools.createdByLabel")}:
            </span>{" "}
            <span className="text-text-secondary">{tool.creator.name}</span>
          </p>
        )}
      </div>

      <div className="flex flex-wrap gap-4 text-sm">
        <a
          href={tool.url}
          target="_blank"
          rel="noopener noreferrer"
          className="text-accent hover:underline"
        >
          {t("tools.links.open")}
        </a>
        {tool.documentation_url && (
          <a
            href={tool.documentation_url}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent hover:underline"
          >
            {t("tools.links.documentation")}
          </a>
        )}
        {tool.video_url && (
          <a
            href={tool.video_url}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent hover:underline"
          >
            {t("tools.links.video")}
          </a>
        )}
      </div>
    </div>
  );
}
