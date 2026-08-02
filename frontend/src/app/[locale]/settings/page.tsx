import { setRequestLocale, getTranslations } from "next-intl/server";
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

        {/* The two sections become navigable in their own phases: categories next,
            users after it. Nothing is linked yet because neither screen exists. */}
        <div className="flex flex-col gap-4">
          <section className="rounded-lg border border-border bg-card p-6">
            <h2 className="text-lg font-medium text-text-primary">
              {t("settings.categories")}
            </h2>
          </section>
          <section className="rounded-lg border border-border bg-card p-6">
            <h2 className="text-lg font-medium text-text-primary">
              {t("settings.users")}
            </h2>
          </section>
        </div>
      </div>
    </RequireRole>
  );
}
