import { setRequestLocale, getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { RequireAuth } from "@/components/require-auth";
import { DashboardGreeting } from "@/components/dashboard-greeting";

export default async function Home({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const t = await getTranslations("common");

  const cards = [
    {
      href: "/tools",
      title: t("dashboard.cards.tools.title"),
      description: t("dashboard.cards.tools.description"),
    },
    {
      href: "/profile",
      title: t("dashboard.cards.profile.title"),
      description: t("dashboard.cards.profile.description"),
    },
  ] as const;

  return (
    <RequireAuth>
      <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 py-8">
        <DashboardGreeting />

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {cards.map((card) => (
            <Link
              key={card.href}
              href={card.href}
              className="flex flex-col gap-2 rounded-lg border border-border bg-card p-6 transition-colors hover:bg-surface"
            >
              <h2 className="text-lg font-medium text-text-primary">
                {card.title}
              </h2>
              <p className="text-sm text-text-secondary">{card.description}</p>
            </Link>
          ))}
        </div>
      </div>
    </RequireAuth>
  );
}
