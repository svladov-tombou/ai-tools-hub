import { setRequestLocale, getTranslations } from "next-intl/server";
import { RequireAuth } from "@/components/require-auth";
import { ProfileView } from "@/components/profile-view";

export default async function ProfilePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const t = await getTranslations("common");

  return (
    <RequireAuth>
      <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8 px-4 py-8">
        <h1 className="text-2xl font-semibold text-text-primary">
          {t("profile.title")}
        </h1>
        <ProfileView />
      </div>
    </RequireAuth>
  );
}
