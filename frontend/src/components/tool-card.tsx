"use client";

import { useTranslations } from "next-intl";
import type { Tool } from "@/types";

export function ToolCard({ tool }: { tool: Tool }) {
  const t = useTranslations("common");

  return (
    <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-6">
      <div className="flex items-start justify-between gap-2">
        <h2 className="text-lg font-medium text-text-primary">{tool.name}</h2>
        {tool.status === "draft" && (
          <span className="shrink-0 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
            {t("tools.draftBadge")}
          </span>
        )}
      </div>

      <p className="text-sm text-text-secondary">{tool.description}</p>

      <div className="flex flex-wrap gap-2">
        {tool.categories.map((category) => (
          <span
            key={category.id}
            className="rounded-full border border-border bg-surface px-2 py-0.5 text-xs text-text-secondary"
          >
            {category.name}
          </span>
        ))}
        {tool.difficulty && (
          <span className="rounded-full border border-border bg-surface px-2 py-0.5 text-xs text-text-secondary">
            {t(`tools.difficulty.${tool.difficulty}`)}
          </span>
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
