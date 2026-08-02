"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import {
  ValidationError,
  createCategory,
  deleteCategory,
  getCategories,
  updateCategory,
} from "@/lib/api";
import { toCategoryName } from "@/lib/category-name";
import { localizedName, sortByLocalizedName } from "@/lib/localized-name";
import { CategoryForm, type CategoryFormValues } from "@/components/category-form";
import { CategoryRow } from "@/components/category-row";
import type { CategoryWithUsage } from "@/types";

/** `{ category: null }` means the create form is open; `null` means no form is open. */
type FormState = { category: CategoryWithUsage | null } | null;

export function CategoriesAdmin() {
  const t = useTranslations("common");
  const locale = useLocale();

  const [categories, setCategories] = useState<CategoryWithUsage[] | null>(null);
  const [loadFailed, setLoadFailed] = useState(false);
  const [reloadToken, setReloadToken] = useState(0);
  const [form, setForm] = useState<FormState>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [listError, setListError] = useState<string | null>(null);

  // The loader lives inside the effect and is re-run by bumping reloadToken, the shape
  // tools-list.tsx already uses. A useCallback called from the effect body trips
  // react-hooks/set-state-in-effect, and the rule is right: it was flagging a real
  // cascading render, not a false positive (pitfalls #5).
  useEffect(() => {
    let isMounted = true;

    async function loadCategories() {
      try {
        const loaded = await getCategories();
        if (!isMounted) return;
        setCategories(loaded);
        setLoadFailed(false);
      } catch {
        if (isMounted) setLoadFailed(true);
      }
    }

    loadCategories();

    return () => {
      isMounted = false;
    };
  }, [reloadToken]);

  function reload() {
    setReloadToken((token) => token + 1);
  }

  function openForm(category: CategoryWithUsage | null) {
    setFormError(null);
    setFieldErrors({});
    setForm({ category });
  }

  async function handleSubmit(values: CategoryFormValues) {
    if (!form) return;

    setIsSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    try {
      const name = toCategoryName(values);

      if (form.category) {
        // No slug: UpdateCategoryPayload has no such field, so an immutable slug cannot
        // be sent even by accident (ADR-28).
        await updateCategory(form.category.id, { name });
      } else {
        await createCategory({ name, slug: values.slug.trim() });
      }

      setForm(null);
      // Reload instead of patching local state: tools_count is computed by the backend.
      reload();
    } catch (err) {
      if (err instanceof ValidationError) {
        setFieldErrors(err.errors);
        setFormError(t("settings.categories.validationFailed"));
      } else {
        setFormError(t("settings.categories.saveError"));
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(category: CategoryWithUsage) {
    const label = localizedName(category.name, locale);
    if (!window.confirm(t("settings.categories.deleteConfirm", { name: label }))) return;

    setListError(null);
    try {
      await deleteCategory(category.id);
    } catch {
      // The button is disabled for a category in use, so reaching here means the count
      // was stale — someone attached a tool since the list was loaded.
      setListError(t("settings.categories.deleteError"));
    } finally {
      reload();
    }
  }

  if (loadFailed) {
    return <p className="text-sm text-error">{t("settings.categories.loadError")}</p>;
  }

  if (categories === null) {
    return <p className="text-text-secondary">{t("settings.categories.loading")}</p>;
  }

  return (
    <div className="flex flex-col gap-6">
      {form ? (
        <CategoryForm
          // Remounts when switching to another category: CategoryForm reads its initial
          // values once, in a useState initializer (the ADR-24 trap).
          key={form.category?.id ?? "new"}
          category={form.category}
          isSubmitting={isSubmitting}
          formError={formError}
          fieldErrors={fieldErrors}
          onSubmit={handleSubmit}
          onCancel={() => setForm(null)}
        />
      ) : (
        <div>
          <button
            type="button"
            onClick={() => openForm(null)}
            className="rounded-md bg-accent px-4 py-2 font-medium text-accent-foreground"
          >
            {t("settings.categories.addButton")}
          </button>
        </div>
      )}

      {listError && <p className="text-sm text-error">{listError}</p>}

      {categories.length === 0 ? (
        <p className="text-text-secondary">{t("settings.categories.empty")}</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {sortByLocalizedName(categories, locale).map((category) => (
            <CategoryRow
              key={category.id}
              category={category}
              onEdit={() => openForm(category)}
              onDelete={() => handleDelete(category)}
            />
          ))}
        </ul>
      )}
    </div>
  );
}
