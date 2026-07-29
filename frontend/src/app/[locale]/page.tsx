import { setRequestLocale, getTranslations } from "next-intl/server";

export default async function Home({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const t = await getTranslations("common");

  return (
    <div className="flex flex-col flex-1 items-center justify-center gap-4">
      <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-6">
        <h1 className="text-text-primary">{t("app.title")}</h1>
        <p className="text-text-secondary">{t("buttons.save")}</p>
        <button
          type="button"
          className="rounded-md bg-accent px-4 py-2 text-accent-foreground"
        >
          {t("buttons.save")}
        </button>
      </div>
    </div>
  );
}
