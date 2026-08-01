"use client";

import { useRouter } from "@/i18n/navigation";
import { createTool } from "@/lib/api";
import type { ToolPayload } from "@/lib/api";
import { ToolForm } from "@/components/tool-form";

export function NewToolForm() {
  const router = useRouter();

  async function handleSubmit(payload: ToolPayload) {
    const tool = await createTool(payload);
    sessionStorage.setItem("tool_created", "1");
    router.push("/tools");
    return tool;
  }

  return <ToolForm onSubmit={handleSubmit} />;
}
