"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { deleteTool, getTools } from "@/lib/api";
import type { Tool } from "@/types";
import { ToolCard } from "@/components/tool-card";
import { ToolsFilters } from "@/components/tools-filters";

export function ToolsList() {
  const t = useTranslations("common");
  const searchParams = useSearchParams();
  const search = searchParams.get("search") ?? "";
  const category = searchParams.get("category") ?? "";
  const role = searchParams.get("role") ?? "";
  const department = searchParams.get("department") ?? "";

  const [tools, setTools] = useState<Tool[]>([]);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(false);
  const [reloadToken, setReloadToken] = useState(0);
  const [savedState] = useState<string | null>(() =>
    typeof window !== "undefined" ? sessionStorage.getItem("tool_saved") : null,
  );
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [deleteSuccess, setDeleteSuccess] = useState(false);
  const [deleteError, setDeleteError] = useState(false);

  useEffect(() => {
    if (savedState) sessionStorage.removeItem("tool_saved");
  }, [savedState]);

  useEffect(() => {
    let isMounted = true;

    async function loadTools() {
      setIsLoading(true);
      setError(false);

      try {
        const page = await getTools({ search, category, role, department });
        if (!isMounted) return;
        setTools(page.tools);
        setTotal(page.total);
      } catch {
        if (!isMounted) return;
        setError(true);
      } finally {
        if (!isMounted) return;
        setIsLoading(false);
      }
    }

    loadTools();

    return () => {
      isMounted = false;
    };
  }, [search, category, role, department, reloadToken]);

  function reload() {
    setReloadToken((token) => token + 1);
  }

  async function handleDelete(tool: Tool) {
    if (!window.confirm(t("tools.deleteConfirm", { name: tool.name }))) return;

    setDeleteSuccess(false);
    setDeleteError(false);
    setDeletingId(tool.id);

    try {
      await deleteTool(tool.id);
      setTools((current) => current.filter((item) => item.id !== tool.id));
      setTotal((current) => Math.max(0, current - 1));
      setDeleteSuccess(true);
    } catch {
      // A 403 or 404 here means the card was stale (someone else deleted the tool, or the
      // caller's rights changed since the list loaded). deleteTool throws one generic Error
      // for every non-OK status, so this branch handles 403/404 and 5xx alike — refetching
      // the list is the right response to all of them.
      setDeleteError(true);
      reload();
    } finally {
      setDeletingId(null);
    }
  }

  const hasActiveFilters = Boolean(search || category || role || department);

  function renderResults() {
    if (isLoading) {
      return <p className="text-text-secondary">{t("tools.loading")}</p>;
    }

    if (error) {
      return <p className="text-error">{t("tools.error")}</p>;
    }

    if (tools.length === 0) {
      return (
        <p className="text-text-secondary">
          {t(hasActiveFilters ? "tools.emptyFiltered" : "tools.empty")}
        </p>
      );
    }

    return (
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {tools.map((tool) => (
            <ToolCard
              key={tool.id}
              tool={tool}
              onDelete={handleDelete}
              isDeleting={deletingId === tool.id}
            />
          ))}
        </div>
        <p className="text-sm text-text-secondary">
          {t("tools.totalCount", { count: total })}
        </p>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      {deleteSuccess ? (
        <p className="rounded-md border border-accent px-3 py-2 text-sm text-text-primary">
          {t("tools.deleteSuccess")}
        </p>
      ) : deleteError ? (
        <p className="text-sm text-error">{t("tools.deleteError")}</p>
      ) : savedState ? (
        <p className="rounded-md border border-accent px-3 py-2 text-sm text-text-primary">
          {savedState === "updated" ? t("tools.form.updated") : t("tools.form.created")}
        </p>
      ) : null}
      <div className="flex justify-end">
        <Link
          href="/tools/new"
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-foreground"
        >
          {t("tools.addButton")}
        </Link>
      </div>
      <ToolsFilters />
      {renderResults()}
    </div>
  );
}
