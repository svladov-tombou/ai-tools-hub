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
    <div className="flex flex-col flex-1 items-center justify-center">
      <h1>{t("app.title")}</h1>
      <p>{t("buttons.save")}</p>
    </div>
  );
}
