import { setRequestLocale, getTranslations } from "next-intl/server";
import { RequireAuth } from "@/components/require-auth";
import { ToolsList } from "@/components/tools-list";

export default async function ToolsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const t = await getTranslations("common");

  return (
    <RequireAuth>
      <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 py-8">
        <h1 className="text-2xl font-semibold text-text-primary">
          {t("tools.title")}
        </h1>
        <ToolsList />
      </div>
    </RequireAuth>
  );
}
