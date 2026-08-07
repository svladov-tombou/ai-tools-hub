"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { createComment, ValidationError } from "@/lib/api";

export function CommentForm({
  toolId,
  onCreated,
}: {
  toolId: number;
  onCreated: () => void;
}) {
  const t = useTranslations("common");

  const [body, setBody] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    try {
      // The created comment is deliberately DISCARDED. The parent re-fetches the first page
      // instead: splicing the response into the list in memory would render a first page that
      // is a lie the moment somebody else commented in between.
      await createComment(toolId, body.trim());
      // Cleared only here, on the success path — a rejected comment must survive the error
      // so the author does not have to retype it.
      setBody("");
      onCreated();
    } catch (err) {
      if (err instanceof ValidationError) {
        setFieldErrors(err.errors);
      } else {
        setFormError(t("tools.comments.genericError"));
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-1">
      <label htmlFor="comment-body" className="text-sm font-medium text-text-primary">
        {t("tools.comments.newLabel")}
      </label>
      {/* Deliberately NO maxLength, unlike the tool description field: the 2000-character
          rule is the backend's, and capping the field here would make it unreachable and
          leave the 422 path untestable. The counter shows the overrun instead. */}
      <textarea
        id="comment-body"
        rows={4}
        value={body}
        placeholder={t("tools.comments.placeholder")}
        onChange={(event) => setBody(event.target.value)}
        className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
      />
      <p className="text-right text-xs text-text-secondary">
        {t("tools.comments.counter", { count: body.length })}
      </p>
      {fieldErrors.body ? <p className="text-xs text-error">{fieldErrors.body[0]}</p> : null}
      {formError ? <p className="text-xs text-error">{formError}</p> : null}
      <p className="text-xs text-text-secondary">{t("tools.comments.immutableHint")}</p>
      <div className="flex">
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-foreground disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting ? t("tools.comments.submitting") : t("tools.comments.submit")}
        </button>
      </div>
    </form>
  );
}
