"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { getComments } from "@/lib/api";
import type { Comment } from "@/lib/api";
import { CommentForm } from "@/components/comment-form";
import { CommentList } from "@/components/comment-list";

export function ToolComments({ toolId }: { toolId: number }) {
  const t = useTranslations("common");

  const [comments, setComments] = useState<Comment[]>([]);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [reloadToken, setReloadToken] = useState(0);

  // The loader is declared INSIDE the effect and re-run by bumping reloadToken. Written as a
  // useCallback it would trip react-hooks/set-state-in-effect; this is the shape tools-list
  // already uses.
  useEffect(() => {
    let isMounted = true;

    async function loadComments() {
      setIsLoading(true);
      setLoadError(false);

      try {
        const page = await getComments(toolId);
        if (!isMounted) return;
        setComments(page.comments);
        setTotal(page.total);
      } catch {
        if (!isMounted) return;
        setLoadError(true);
      } finally {
        if (!isMounted) return;
        setIsLoading(false);
      }
    }

    loadComments();

    return () => {
      isMounted = false;
    };
  }, [toolId, reloadToken]);

  function renderList() {
    if (isLoading) {
      return <p className="text-sm text-text-secondary">{t("tools.comments.loading")}</p>;
    }

    if (loadError) {
      return <p className="text-sm text-error">{t("tools.comments.loadError")}</p>;
    }

    if (comments.length === 0) {
      return <p className="text-sm text-text-secondary">{t("tools.comments.empty")}</p>;
    }

    return (
      <div className="flex flex-col gap-4">
        <CommentList comments={comments} />
        <p className="text-sm text-text-secondary">
          {t("tools.comments.totalCount", { count: total })}
        </p>
      </div>
    );
  }

  return (
    <section className="flex flex-col gap-4 border-t border-border pt-6">
      <h2 className="text-lg font-semibold text-text-primary">
        {t("tools.comments.heading")}
      </h2>
      {/* Form first, list under it: the newest comment is at the top of the list, so the
          field sits next to where the result appears. */}
      <CommentForm toolId={toolId} onCreated={() => setReloadToken((token) => token + 1)} />
      {renderList()}
    </section>
  );
}
