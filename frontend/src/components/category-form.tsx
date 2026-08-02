"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import type { CategoryWithUsage } from "@/types";

export type CategoryFormValues = {
  bg: string;
  en: string;
  fr: string;
  slug: string;
};

const INPUT_CLASS =
  "rounded-md border border-border bg-surface px-3 py-2 text-text-primary outline-none focus:border-accent";

/**
 * Create and edit share this form. `category === null` means create.
 *
 * In edit mode the slug is rendered as TEXT, not as a disabled input: it is immutable
 * after creation (ADR-28) and `UpdateCategoryPayload` has no slug field at all, so the
 * value is never collected and never sent. A greyed-out input would still hold a value
 * and invite someone to wire it up later.
 *
 * The parent must give this component a `key` tied to the category being edited — the
 * initial values are read once in a useState initializer (the ADR-24 trap), so without
 * a remount, switching to another category would keep the previous one's values.
 */
export function CategoryForm({
  category,
  isSubmitting,
  formError,
  fieldErrors,
  onSubmit,
  onCancel,
}: {
  category: CategoryWithUsage | null;
  isSubmitting: boolean;
  formError: string | null;
  fieldErrors: Record<string, string[]>;
  onSubmit: (values: CategoryFormValues) => void;
  onCancel: () => void;
}) {
  const t = useTranslations("common");
  const isEdit = category !== null;

  const [values, setValues] = useState<CategoryFormValues>(() => ({
    bg: category?.name.bg ?? "",
    en: category?.name.en ?? "",
    fr: category?.name.fr ?? "",
    slug: category?.slug ?? "",
  }));

  function set(field: keyof CategoryFormValues, value: string) {
    setValues((current) => ({ ...current, [field]: value }));
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit(values);
  }

  const fields = [
    { key: "bg" as const, label: t("settings.categories.nameBg"), errorKey: "name.bg", required: true },
    { key: "en" as const, label: t("settings.categories.nameEn"), errorKey: "name.en", required: false },
    { key: "fr" as const, label: t("settings.categories.nameFr"), errorKey: "name.fr", required: false },
  ];

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-col gap-4 rounded-lg border border-border bg-card p-6"
    >
      <h2 className="text-lg font-medium text-text-primary">
        {isEdit ? t("settings.categories.editTitle") : t("settings.categories.newTitle")}
      </h2>

      {fields.map((field) => (
        <div key={field.key} className="flex flex-col gap-1.5">
          <label htmlFor={`category-${field.key}`} className="text-sm text-text-secondary">
            {field.label}
          </label>
          <input
            id={`category-${field.key}`}
            type="text"
            value={values[field.key]}
            onChange={(event) => set(field.key, event.target.value)}
            required={field.required}
            maxLength={255}
            className={INPUT_CLASS}
          />
          {fieldErrors[field.errorKey] && (
            <p className="text-sm text-error">{fieldErrors[field.errorKey][0]}</p>
          )}
        </div>
      ))}

      <div className="flex flex-col gap-1.5">
        <span className="text-sm text-text-secondary">{t("settings.categories.slugLabel")}</span>
        {isEdit ? (
          <p className="text-text-primary">{category.slug}</p>
        ) : (
          <input
            id="category-slug"
            type="text"
            value={values.slug}
            onChange={(event) => set("slug", event.target.value)}
            required
            maxLength={255}
            className={INPUT_CLASS}
          />
        )}
        <p className="text-sm text-text-secondary">{t("settings.categories.slugHint")}</p>
        {fieldErrors.slug && <p className="text-sm text-error">{fieldErrors.slug[0]}</p>}
      </div>

      {formError && <p className="text-sm text-error">{formError}</p>}

      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-md bg-accent px-4 py-2 font-medium text-accent-foreground disabled:opacity-60"
        >
          {t("buttons.save")}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-md border border-border px-4 py-2 text-text-primary hover:bg-surface"
        >
          {t("buttons.cancel")}
        </button>
      </div>
    </form>
  );
}
