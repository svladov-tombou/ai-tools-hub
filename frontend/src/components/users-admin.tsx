"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useAuth } from "@/lib/auth-context";
import {
  ValidationError,
  activateUser,
  createUser,
  deactivateUser,
  getDepartments,
  getRoles,
  getUsers,
  updateUser,
  updateUserPassword,
  updateUserRoles,
} from "@/lib/api";
import type {
  CreateUserPayload,
  UpdateUserPasswordPayload,
  UpdateUserPayload,
  UpdateUserRolesPayload,
} from "@/lib/api";
import { UserCreateForm } from "@/components/user-create-form";
import { UserEditPanel, type BlockState } from "@/components/user-edit-panel";
import { UserList } from "@/components/user-list";
import type { AdminUser, Department, RoleOption } from "@/types";

/** `{ mode: "create" }` opens the create form; `{ mode: "edit", userId }` opens that user's
 *  edit panel; `null` means nothing is open.
 *
 *  It holds an ID, not the user object. Storing the object would freeze a copy taken when
 *  the panel opened, so after saving a rename the panel heading would keep announcing the
 *  old name while the row underneath already showed the new one. Deriving the user from
 *  the reloaded list removes that whole class of staleness instead of resyncing it. */
type PanelState = { mode: "create" } | { mode: "edit"; userId: number } | null;

const EMPTY_BLOCK: BlockState = { isSubmitting: false, error: null, fieldErrors: {} };

export function UsersAdmin() {
  const t = useTranslations("common");
  const { user: actor } = useAuth();

  const [users, setUsers] = useState<AdminUser[] | null>(null);
  const [roles, setRoles] = useState<RoleOption[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [loadFailed, setLoadFailed] = useState(false);
  const [reloadToken, setReloadToken] = useState(0);

  const [panel, setPanel] = useState<PanelState>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [listError, setListError] = useState<string | null>(null);

  const [createState, setCreateState] = useState<BlockState>(EMPTY_BLOCK);
  const [detailsState, setDetailsState] = useState<BlockState>(EMPTY_BLOCK);
  const [rolesState, setRolesState] = useState<BlockState>(EMPTY_BLOCK);
  const [passwordState, setPasswordState] = useState<BlockState>(EMPTY_BLOCK);
  const [passwordFormVersion, setPasswordFormVersion] = useState(0);

  // Same shape as categories-admin: the loader lives inside the effect and is re-run by
  // bumping reloadToken. A useCallback called from the effect body trips
  // react-hooks/set-state-in-effect, and that rule is right, so it is not worked around.
  useEffect(() => {
    let isMounted = true;

    async function load() {
      try {
        const [loadedUsers, loadedRoles, loadedDepartments] = await Promise.all([
          getUsers(),
          getRoles(),
          getDepartments(),
        ]);
        if (!isMounted) return;
        setUsers(loadedUsers);
        setRoles(loadedRoles);
        setDepartments(loadedDepartments);
        setLoadFailed(false);
      } catch {
        if (isMounted) setLoadFailed(true);
      }
    }

    load();

    return () => {
      isMounted = false;
    };
  }, [reloadToken]);

  function reload() {
    setReloadToken((token) => token + 1);
  }

  function toBlockState(err: unknown): BlockState {
    if (err instanceof ValidationError) {
      return {
        isSubmitting: false,
        error: t("settings.users.validationFailed"),
        fieldErrors: err.errors,
      };
    }
    return { isSubmitting: false, error: t("settings.users.saveError"), fieldErrors: {} };
  }

  function openCreate() {
    setCreateState(EMPTY_BLOCK);
    setNotice(null);
    setPanel({ mode: "create" });
  }

  function openEdit(target: AdminUser) {
    setDetailsState(EMPTY_BLOCK);
    setRolesState(EMPTY_BLOCK);
    setPasswordState(EMPTY_BLOCK);
    setNotice(null);
    setPanel({ mode: "edit", userId: target.id });
  }

  async function handleCreate(payload: CreateUserPayload) {
    setCreateState({ isSubmitting: true, error: null, fieldErrors: {} });
    try {
      await createUser(payload);
      setPanel(null);
      setNotice(t("settings.users.userCreated"));
      reload();
    } catch (err) {
      setCreateState(toBlockState(err));
    }
  }

  async function handleSaveDetails(id: number, payload: UpdateUserPayload) {
    setDetailsState({ isSubmitting: true, error: null, fieldErrors: {} });
    try {
      await updateUser(id, payload);
      setDetailsState(EMPTY_BLOCK);
      setNotice(t("settings.users.detailsSaved"));
      reload();
    } catch (err) {
      setDetailsState(toBlockState(err));
    }
  }

  async function handleSaveRoles(id: number, payload: UpdateUserRolesPayload) {
    setRolesState({ isSubmitting: true, error: null, fieldErrors: {} });
    try {
      await updateUserRoles(id, payload);
      setRolesState(EMPTY_BLOCK);
      setNotice(t("settings.users.rolesSaved"));
      reload();
    } catch (err) {
      setRolesState(toBlockState(err));
    }
  }

  async function handleSavePassword(id: number, payload: UpdateUserPasswordPayload) {
    setPasswordState({ isSubmitting: true, error: null, fieldErrors: {} });
    try {
      await updateUserPassword(id, payload);
      setPasswordState(EMPTY_BLOCK);
      // Nothing visible needs a reload for a password change; the form is remounted
      // (fresh key) so it clears itself instead of holding the just-submitted password.
      setPasswordFormVersion((version) => version + 1);
      setNotice(t("settings.users.passwordSaved"));
    } catch (err) {
      setPasswordState(toBlockState(err));
    }
  }

  async function handleActivate(target: AdminUser) {
    setListError(null);
    setNotice(null);
    try {
      await activateUser(target.id);
      reload();
    } catch {
      setListError(t("settings.users.statusError"));
    }
  }

  async function handleDeactivate(target: AdminUser) {
    if (!window.confirm(t("settings.users.deactivateConfirm", { name: target.name }))) return;

    setListError(null);
    setNotice(null);
    try {
      await deactivateUser(target.id);
      reload();
    } catch {
      setListError(t("settings.users.statusError"));
    }
  }

  if (loadFailed) {
    return <p className="text-sm text-error">{t("settings.users.loadError")}</p>;
  }

  if (users === null) {
    return <p className="text-text-secondary">{t("settings.users.loading")}</p>;
  }

  // Read from the freshly loaded list, never from a copy held in panel state.
  const editingUser =
    panel?.mode === "edit" ? (users.find((candidate) => candidate.id === panel.userId) ?? null) : null;

  return (
    <div className="flex flex-col gap-6">
      {panel === null && (
        <div>
          <button
            type="button"
            onClick={openCreate}
            className="rounded-md bg-accent px-4 py-2 font-medium text-accent-foreground"
          >
            {t("settings.users.addButton")}
          </button>
        </div>
      )}

      {panel?.mode === "create" && (
        <UserCreateForm
          roles={roles}
          departments={departments}
          actor={actor}
          isSubmitting={createState.isSubmitting}
          formError={createState.error}
          fieldErrors={createState.fieldErrors}
          onSubmit={handleCreate}
          onCancel={() => setPanel(null)}
        />
      )}

      {editingUser && (
        <UserEditPanel
          // Remounts when switching to another user's row (the ADR-24 trap).
          key={editingUser.id}
          user={editingUser}
          actor={actor}
          roles={roles}
          departments={departments}
          detailsState={detailsState}
          rolesState={rolesState}
          passwordState={passwordState}
          passwordFormVersion={passwordFormVersion}
          onSaveDetails={(payload) => handleSaveDetails(editingUser.id, payload)}
          onSaveRoles={(payload) => handleSaveRoles(editingUser.id, payload)}
          onSavePassword={(payload) => handleSavePassword(editingUser.id, payload)}
          onClose={() => setPanel(null)}
        />
      )}

      {notice && <p className="text-sm text-accent">{notice}</p>}
      {listError && <p className="text-sm text-error">{listError}</p>}

      <UserList
        users={users}
        actor={actor}
        departments={departments}
        onEdit={openEdit}
        onActivate={handleActivate}
        onDeactivate={handleDeactivate}
      />
    </div>
  );
}
