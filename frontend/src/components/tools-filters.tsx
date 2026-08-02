"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useRouter, usePathname } from "@/i18n/navigation";
import { getCategories, getRoles, getDepartments } from "@/lib/api";
import { localizedName, sortByLocalizedName } from "@/lib/localized-name";
import type { Category, Department, RoleOption } from "@/types";

function withParam(
  current: URLSearchParams,
  key: string,
  value: string,
): string {
  const params = new URLSearchParams(current);

  if (value) {
    params.set(key, value);
  } else {
    params.delete(key);
  }

  return params.toString();
}

export function ToolsFilters() {
  const t = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const [searchText, setSearchText] = useState(
    searchParams.get("search") ?? "",
  );
  const [categories, setCategories] = useState<Category[]>([]);
  const [roles, setRoles] = useState<RoleOption[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [isLoadingOptions, setIsLoadingOptions] = useState(true);

  useEffect(() => {
    let isMounted = true;

    Promise.all([getCategories(), getRoles(), getDepartments()])
      .then(([categoriesData, rolesData, departmentsData]) => {
        if (!isMounted) return;
        setCategories(categoriesData);
        setRoles(rolesData);
        setDepartments(departmentsData);
      })
      .catch(() => {})
      .finally(() => {
        if (!isMounted) return;
        setIsLoadingOptions(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    const timeout = setTimeout(() => {
      const trimmed = searchText.trim();
      const queryString = withParam(
        searchParams,
        "search",
        trimmed.length >= 3 ? trimmed : "",
      );
      if (queryString === searchParams.toString()) return;
      router.replace(`${pathname}${queryString ? `?${queryString}` : ""}`);
    }, 500);

    return () => clearTimeout(timeout);
  }, [searchText, searchParams, pathname, router]);

  function updateParam(key: string, value: string) {
    const queryString = withParam(searchParams, key, value);
    router.replace(`${pathname}${queryString ? `?${queryString}` : ""}`);
  }

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
      <input
        type="text"
        value={searchText}
        onChange={(event) => setSearchText(event.target.value)}
        placeholder={t("tools.filters.searchPlaceholder")}
        aria-label={t("tools.filters.searchAriaLabel")}
        className="flex-1 rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
      />

      <select
        disabled={isLoadingOptions}
        value={searchParams.get("category") ?? ""}
        onChange={(event) => updateParam("category", event.target.value)}
        aria-label={t("tools.filters.categoryLabel")}
        className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
      >
        <option value="">{t("tools.filters.allCategories")}</option>
        {sortByLocalizedName(categories, locale).map((category) => (
          <option key={category.id} value={category.slug}>
            {localizedName(category.name, locale)}
          </option>
        ))}
      </select>

      <select
        disabled={isLoadingOptions}
        value={searchParams.get("role") ?? ""}
        onChange={(event) => updateParam("role", event.target.value)}
        aria-label={t("tools.filters.roleLabel")}
        className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
      >
        <option value="">{t("tools.filters.allRoles")}</option>
        {roles.map((role) => (
          <option key={role.id} value={role.name}>
            {t(`roles.${role.name}`)}
          </option>
        ))}
      </select>

      <select
        disabled={isLoadingOptions}
        value={searchParams.get("department") ?? ""}
        onChange={(event) => updateParam("department", event.target.value)}
        aria-label={t("tools.filters.departmentLabel")}
        className="rounded-md border border-border bg-card px-3 py-2 text-sm text-text-primary"
      >
        <option value="">{t("tools.filters.allDepartments")}</option>
        {departments.map((department) => (
          <option key={department.id} value={department.slug}>
            {t(`departments.${department.slug}`)}
          </option>
        ))}
      </select>
    </div>
  );
}
