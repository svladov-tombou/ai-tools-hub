import { setRequestLocale, getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { PLACEHOLDER_USER } from "@/lib/placeholder-user";
import { formatRoles } from "@/lib/format-roles";

export default async function Home({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const t = await getTranslations("common");
  const roles = formatRoles(PLACEHOLDER_USER.roles, locale, (role) =>
    t(`roles.${role}`),
  );

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
    <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 py-8">
      <div className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold text-text-primary">
          {t("dashboard.greeting", { name: PLACEHOLDER_USER.name })}
        </h1>
        <p className="text-text-secondary">
          {t("dashboard.rolesLabel", { roles })}
        </p>
      </div>

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
  );
}
