"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { getTools } from "@/lib/api";
import type { Tool } from "@/types";
import { ToolCard } from "@/components/tool-card";
import { ToolsFilters } from "@/components/tools-filters";

export function ToolsList() {
  const t = useTranslations("common");
  const searchParams = useSearchParams();
  const search = searchParams.get("search") ?? "";
  const category = searchParams.get("category") ?? "";
  const role = searchParams.get("role") ?? "";

  const [tools, setTools] = useState<Tool[]>([]);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    let isMounted = true;

    async function loadTools() {
      setIsLoading(true);
      setError(false);

      try {
        const page = await getTools({ search, category, role });
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
  }, [search, category, role]);

  const hasActiveFilters = Boolean(search || category || role);

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
            <ToolCard key={tool.id} tool={tool} />
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
      <ToolsFilters />
      {renderResults()}
    </div>
  );
}
