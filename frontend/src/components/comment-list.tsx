"use client";

import { useMemo } from "react";
import { useLocale, useTranslations } from "next-intl";
import type { Comment } from "@/lib/api";

export function CommentList({ comments }: { comments: Comment[] }) {
  const t = useTranslations("common");
  const locale = useLocale();

  // Built once per locale rather than per row: a formatter is expensive and the list can
  // hold fifty of them. No date library — Intl is already the project's tool for this
  // (`format-roles.ts` uses Intl.ListFormat the same way).
  const formatter = useMemo(
    () => new Intl.DateTimeFormat(locale, { dateStyle: "medium", timeStyle: "short" }),
    [locale],
  );

  return (
    <ul className="flex flex-col gap-4">
      {comments.map((comment) => (
        <li
          key={comment.id}
          className="flex flex-col gap-2 rounded-lg border border-border bg-card p-4"
        >
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            {/* Never blank and never "null": an authorless comment says so in words. */}
            <span className="text-sm font-medium text-text-primary">
              {comment.user ? comment.user.name : t("tools.comments.deletedAuthor")}
            </span>
            <time dateTime={comment.created_at} className="text-xs text-text-secondary">
              {formatter.format(new Date(comment.created_at))}
            </time>
          </div>
          {/* Same treatment as the tool description: the author's line breaks survive and a
              long unbroken token wraps instead of widening the page. */}
          <p className="whitespace-pre-wrap break-words text-sm text-text-secondary">
            {comment.body}
          </p>
        </li>
      ))}
    </ul>
  );
}
