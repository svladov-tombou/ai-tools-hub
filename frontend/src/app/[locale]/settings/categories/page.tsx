import { setRequestLocale, getTranslations } from "next-intl/server";
import { RequireRole } from "@/components/require-role";
import { CategoriesAdmin } from "@/components/categories-admin";
import { ADMIN_ROLES } from "@/lib/roles";

export default async function SettingsCategoriesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const t = await getTranslations("common");

  return (
    <RequireRole roles={ADMIN_ROLES}>
      <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8 px-4 py-8">
        <h1 className="text-2xl font-semibold text-text-primary">
          {t("settings.categories.title")}
        </h1>
        <CategoriesAdmin />
      </div>
    </RequireRole>
  );
}
