"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { getCategories, getRoles, getDepartments, ValidationError } from "@/lib/api";
import type { ToolPayload } from "@/lib/api";
import { localizedName, sortByLocalizedName } from "@/lib/localized-name";
import { CheckboxGroup } from "@/components/checkbox-group";
import { useAuth } from "@/lib/auth-context";
import { canPublish } from "@/lib/roles";
import type { Category, Department, RoleOption, Difficulty, ToolStatus } from "@/types";

type ToolFormProps = {
  initialValues?: ToolPayload;
  onSubmit: (payload: ToolPayload) => Promise<unknown>;
};

const EMPTY: ToolPayload = {
  name: "",
  description: "",
  url: "",
  documentation_url: null,
  video_url: null,
  difficulty: null,
  status: "published",
  category_ids: [],
  role_ids: [],
  department_ids: [],
};

const DIFFICULTIES: Difficulty[] = ["beginner", "intermediate", "advanced"];
const STATUSES: ToolStatus[] = ["draft", "published"];

export function ToolForm({ initialValues, onSubmit }: ToolFormProps) {
  const t = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const { user } = useAuth();
  const mayPublish = canPublish(user);

  const [values, setValues] = useState<ToolPayload>(initialValues ?? EMPTY);
  const [categories, setCategories] = useState<Category[]>([]);
  const [roles, setRoles] = useState<RoleOption[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [isLoadingOptions, setIsLoadingOptions] = useState(true);
  const [optionsError, setOptionsError] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    let isMounted = true;

    Promise.all([getCategories(), getRoles(), getDepartments()])
      .then(([categoriesData, rolesData, departmentsData]) => {
        if (!isMounted) return;
        setCategories(categoriesData);
        setRoles(rolesData);
        setDepartments(departmentsData);
      })
      .catch(() => {
        if (!isMounted) return;
        setOptionsError(true);
      })
      .finally(() => {
        if (!isMounted) return;
        setIsLoadingOptions(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  function setField<K extends keyof ToolPayload>(key: K, value: ToolPayload[K]) {
    setValues((current) => ({ ...current, [key]: value }));
  }

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    const payload: ToolPayload = {
      ...values,
      name: values.name.trim(),
      description: values.description.trim(),
      url: values.url.trim(),
      documentation_url: values.documentation_url?.trim() ? values.documentation_url.trim() : null,
      video_url: values.video_url?.trim() ? values.video_url.trim() : null,
    };

    // A non-publisher never sends `status`. The backend forces a draft on create and leaves the
    // stored status untouched on update — whereas sending the value the form loaded would make an
    // employee's own PUBLISHED tool fail with 403 on every save.
    if (!mayPublish) {
      delete payload.status;
    }

    try {
      await onSubmit(payload);
    } catch (err) {
      if (err instanceof ValidationError) {
        setFieldErrors(err.errors);
        setFormError(t("tools.form.validationFailed"));
      } else {
        setFormError(t("tools.form.genericError"));
      }
      setIsSubmitting(false);
    }
  }

  function handleCancel() {
    const changed = JSON.stringify(values) !== JSON.stringify(initialValues ?? EMPTY);
    if (changed && !window.confirm(t("tools.form.confirmCancel"))) return;
    router.back();
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {optionsError ? (
        <p className="rounded-md border border-error px-3 py-2 text-sm text-error">
          {t("tools.form.optionsError")}
        </p>
      ) : null}

      <div className="flex flex-col gap-1">
        <label htmlFor="tool-name" className="text-sm font-medium text-text-primary">{t("tools.form.nameLabel")}</label>
        <input
          id="tool-name"
          type="text"
          value={values.name}
          onChange={(event) => setField("name", event.target.value)}
          className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
        />
        {fieldErrors.name ? <p className="text-xs text-error">{fieldErrors.name[0]}</p> : null}
      </div>

      <div className="flex flex-col gap-1">
        <label htmlFor="tool-description" className="text-sm font-medium text-text-primary">{t("tools.form.descriptionLabel")}</label>
        <textarea
          id="tool-description"
          rows={10}
          maxLength={5000}
          value={values.description}
          onChange={(event) => setField("description", event.target.value)}
          className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
        />
        <p className="text-right text-xs text-text-secondary">
          {t("tools.form.descriptionCounter", { count: values.description.length })}
        </p>
        {fieldErrors.description ? (
          <p className="text-xs text-error">{fieldErrors.description[0]}</p>
        ) : null}
      </div>

      <div className="flex flex-col gap-1">
        <label htmlFor="tool-url" className="text-sm font-medium text-text-primary">{t("tools.form.urlLabel")}</label>
        <input
          id="tool-url"
          type="text"
          value={values.url}
          onChange={(event) => setField("url", event.target.value)}
          className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
        />
        {fieldErrors.url ? <p className="text-xs text-error">{fieldErrors.url[0]}</p> : null}
      </div>

      <div className="flex flex-col gap-1">
        <label htmlFor="tool-documentation-url" className="text-sm font-medium text-text-primary">
          {t("tools.form.documentationUrlLabel")} {t("tools.form.optional")}
        </label>
        <input
          id="tool-documentation-url"
          type="text"
          value={values.documentation_url ?? ""}
          onChange={(event) => {
            const trimmed = event.target.value.trim();
            setField("documentation_url", trimmed === "" ? null : event.target.value);
          }}
          className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
        />
        {fieldErrors.documentation_url ? (
          <p className="text-xs text-error">{fieldErrors.documentation_url[0]}</p>
        ) : null}
      </div>

      <div className="flex flex-col gap-1">
        <label htmlFor="tool-video-url" className="text-sm font-medium text-text-primary">
          {t("tools.form.videoUrlLabel")} {t("tools.form.optional")}
        </label>
        <input
          id="tool-video-url"
          type="text"
          value={values.video_url ?? ""}
          onChange={(event) => {
            const trimmed = event.target.value.trim();
            setField("video_url", trimmed === "" ? null : event.target.value);
          }}
          className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
        />
        {fieldErrors.video_url ? <p className="text-xs text-error">{fieldErrors.video_url[0]}</p> : null}
      </div>

      <div className="flex flex-col gap-1">
        <label htmlFor="tool-difficulty" className="text-sm font-medium text-text-primary">{t("tools.form.difficultyLabel")}</label>
        <select
          id="tool-difficulty"
          value={values.difficulty ?? ""}
          onChange={(event) =>
            setField("difficulty", event.target.value === "" ? null : (event.target.value as Difficulty))
          }
          className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
        >
          <option value="">{t("tools.form.difficultyNone")}</option>
          {DIFFICULTIES.map((difficulty) => (
            <option key={difficulty} value={difficulty}>
              {t(`tools.difficulty.${difficulty}`)}
            </option>
          ))}
        </select>
        {fieldErrors.difficulty ? <p className="text-xs text-error">{fieldErrors.difficulty[0]}</p> : null}
      </div>

      {mayPublish ? (
        <div className="flex flex-col gap-1">
          <label htmlFor="tool-status" className="text-sm font-medium text-text-primary">{t("tools.form.statusLabel")}</label>
          <select
            id="tool-status"
            value={values.status}
            onChange={(event) => setField("status", event.target.value as ToolStatus)}
            className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
          >
            {STATUSES.map((status) => (
              <option key={status} value={status}>
                {t(`tools.form.status.${status}`)}
              </option>
            ))}
          </select>
          {fieldErrors.status ? <p className="text-xs text-error">{fieldErrors.status[0]}</p> : null}
        </div>
      ) : (
        // Two wordings, because one cannot be true in both modes: creating really does produce a
        // draft, while editing leaves the stored status alone — including a published one.
        <p className="text-xs text-text-secondary">
          {initialValues ? t("tools.form.statusHintEdit") : t("tools.form.statusHintCreate")}
        </p>
      )}

      <CheckboxGroup
        heading={t("tools.form.categoriesHeading")}
        columns={3}
        options={sortByLocalizedName(categories, locale).map((c) => ({
          id: c.id,
          label: localizedName(c.name, locale),
        }))}
        selectedIds={values.category_ids}
        onChange={(ids) => setField("category_ids", ids)}
        disabled={isLoadingOptions}
      />

      <CheckboxGroup
        heading={t("tools.form.rolesHeading")}
        columns={2}
        hint={t("tools.form.rolesHint")}
        options={roles.map((r) => ({ id: r.id, label: t(`roles.${r.name}`) }))}
        selectedIds={values.role_ids}
        onChange={(ids) => setField("role_ids", ids)}
        disabled={isLoadingOptions}
      />

      <CheckboxGroup
        heading={t("tools.form.departmentsHeading")}
        columns={3}
        bulkActions={{
          selectAllLabel: t("tools.form.selectAll"),
          clearAllLabel: t("tools.form.clearAll"),
        }}
        options={departments.map((d) => ({ id: d.id, label: t(`departments.${d.slug}`) }))}
        selectedIds={values.department_ids}
        onChange={(ids) => setField("department_ids", ids)}
        disabled={isLoadingOptions}
      />

      {formError ? (
        <p className="rounded-md border border-error px-3 py-2 text-sm text-error">{formError}</p>
      ) : null}
      <div className="flex gap-3">
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-foreground disabled:opacity-60"
        >
          {isSubmitting ? t("tools.form.submitting") : t("buttons.save")}
        </button>
        <button
          type="button"
          onClick={handleCancel}
          className="rounded-md border border-border px-4 py-2 text-sm text-text-primary"
        >
          {t("buttons.cancel")}
        </button>
      </div>
    </form>
  );
}
