"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { getTool, updateTool } from "@/lib/api";
import type { ToolPayload } from "@/lib/api";
import { ToolForm } from "@/components/tool-form";
import type { Tool } from "@/types";

function toPayload(tool: Tool): ToolPayload {
  return {
    name: tool.name,
    description: tool.description,
    url: tool.url,
    documentation_url: tool.documentation_url,
    video_url: tool.video_url,
    difficulty: tool.difficulty,
    status: tool.status,
    category_ids: tool.categories.map((c) => c.id),
    role_ids: tool.roles.map((r) => r.id),
    department_ids: tool.departments.map((d) => d.id),
  };
}

export function EditToolForm({ id }: { id: number }) {
  const t = useTranslations("common");
  const router = useRouter();

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

  async function handleSubmit(payload: ToolPayload) {
    const updated = await updateTool(id, payload);
    sessionStorage.setItem("tool_saved", "updated");
    router.push("/tools");
    return updated;
  }

  if (isLoading) {
    return <p className="text-text-secondary">{t("tools.form.loadingTool")}</p>;
  }

  if (loadError || tool === null) {
    return <p className="text-error">{t("tools.form.loadError")}</p>;
  }

  return (
    <div className="flex flex-col gap-8">
      <h1 className="text-2xl font-semibold text-text-primary">
        {t("tools.form.editTitle", { name: tool.name })}
      </h1>
      <ToolForm initialValues={toPayload(tool)} onSubmit={handleSubmit} />
    </div>
  );
}
