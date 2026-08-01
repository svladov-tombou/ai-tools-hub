import { setRequestLocale } from "next-intl/server";
import { RequireAuth } from "@/components/require-auth";
import { EditToolForm } from "@/components/edit-tool-form";

export default async function EditToolPage({
  params,
}: {
  params: Promise<{ locale: string; id: string }>;
}) {
  const { locale, id } = await params;
  setRequestLocale(locale);
  const toolId = Number(id);

  return (
    <RequireAuth>
      <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8 px-4 py-8">
        <EditToolForm id={toolId} />
      </div>
    </RequireAuth>
  );
}
