"use client";

import type { ReactNode } from "react";
import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth-context";
import { getTool } from "@/lib/api";
import { localizedName } from "@/lib/localized-name";
import { ADMIN_ROLES, hasAnyRole } from "@/lib/roles";
import { ToolComments } from "@/components/tool-comments";
import type { Tool } from "@/types";

/** One "Label: value" row. The card spells each block out; here the shape repeats five times. */
function MetaRow({ label, value }: { label: string; value: string }) {
  return (
    <p>
      <span className="font-semibold text-text-primary">{label}:</span>{" "}
      <span className="text-text-secondary">{value}</span>
    </p>
  );
}

function ExternalLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="text-accent hover:underline"
    >
      {children}
    </a>
  );
}

export function ToolDetail({ id }: { id: number }) {
  const t = useTranslations("common");
  const locale = useLocale();
  const { user } = useAuth();

  const [tool, setTool] = useState<Tool | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);

  useEffect(() => {
    let isMounted = true;

    getTool(id)
      .then((data) => {
        if (!isMounted) return;
        setTool(data);
      })
      .catch(() => {
        if (!isMounted) return;
        setLoadError(true);
      })
      .finally(() => {
        if (!isMounted) return;
        setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [id]);

  if (isLoading) {
    return <p className="text-text-secondary">{t("tools.detail.loading")}</p>;
  }

  if (loadError || tool === null) {
    return <p className="text-error">{t("tools.detail.loadError")}</p>;
  }

  // Same rule as the card: the author, or an administrator. UX only — ToolPolicy is the
  // real boundary, and this screen never exposes a write action of its own.
  const canEdit = Boolean(
    user && (tool.created_by === user.id || hasAnyRole(user, ADMIN_ROLES)),
  );

  return (
    <div className="flex flex-col gap-6">
      <Link href="/tools" className="text-sm text-accent hover:underline">
        {t("tools.detail.backToList")}
      </Link>

      <div className="flex items-start justify-between gap-2">
        <h1 className="text-2xl font-semibold text-text-primary">{tool.name}</h1>
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

      {/* The whole description, unclamped. It is plain text: no Markdown renderer, so the
          author's own line breaks are preserved by CSS and long tokens wrap instead of
          widening the page. */}
      <p className="whitespace-pre-wrap break-words text-text-secondary">
        {tool.description}
      </p>

      <div className="flex flex-col gap-2 text-sm">
        {tool.categories.length > 0 && (
          <MetaRow
            label={t("tools.categoriesLabel")}
            value={tool.categories
              .map((category) => localizedName(category.name, locale))
              .join(", ")}
          />
        )}

        {tool.departments.length > 0 && (
          <MetaRow
            label={t("tools.departmentsLabel")}
            value={tool.departments
              .map((department) => t(`departments.${department.slug}`))
              .join(", ")}
          />
        )}

        {tool.roles.length > 0 && (
          <MetaRow
            label={t("tools.rolesLabel")}
            value={tool.roles.map((role) => t(`roles.${role.name}`)).join(", ")}
          />
        )}

        {tool.difficulty && (
          <MetaRow
            label={t("tools.difficultyLabel")}
            value={t(`tools.difficulty.${tool.difficulty}`)}
          />
        )}

        {/* Hidden entirely when absent: created_by is nullable with nullOnDelete (ADR-11),
            so a removed user leaves an authorless tool. Same as the card. */}
        {tool.creator && (
          <MetaRow label={t("tools.createdByLabel")} value={tool.creator.name} />
        )}
      </div>

      <div className="flex flex-wrap gap-4 text-sm">
        <ExternalLink href={tool.url}>{t("tools.links.open")}</ExternalLink>
        {tool.documentation_url && (
          <ExternalLink href={tool.documentation_url}>
            {t("tools.links.documentation")}
          </ExternalLink>
        )}
        {tool.video_url && (
          <ExternalLink href={tool.video_url}>{t("tools.links.video")}</ExternalLink>
        )}
      </div>

      <ToolComments toolId={tool.id} />
    </div>
  );
}
