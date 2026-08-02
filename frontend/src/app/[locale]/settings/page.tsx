import { setRequestLocale, getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { RequireRole } from "@/components/require-role";
import { ADMIN_ROLES } from "@/lib/roles";

export default async function SettingsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const t = await getTranslations("common");

  return (
    <RequireRole roles={ADMIN_ROLES}>
      <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 py-8">
        <h1 className="text-2xl font-semibold text-text-primary">
          {t("settings.title")}
        </h1>

        {/* Users becomes a link in its own phase; its screen does not exist yet. */}
        <div className="flex flex-col gap-4">
          <Link
            href="/settings/categories"
            className="rounded-lg border border-border bg-card p-6 hover:border-accent"
          >
            <h2 className="text-lg font-medium text-text-primary">
              {t("settings.categories.title")}
            </h2>
          </Link>
          <section className="rounded-lg border border-border bg-card p-6">
            <h2 className="text-lg font-medium text-text-primary">
              {t("settings.users.title")}
            </h2>
          </section>
        </div>
      </div>
    </RequireRole>
  );
}
