import { routing } from "@/i18n/routing";
import type {
  AdminUser,
  Category,
  CategoryWithUsage,
  Department,
  Difficulty,
  Role,
  RoleOption,
  Tool,
  ToolStatus,
} from "@/types";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost/api";
const TOKEN_KEY = "auth_token";

export class ValidationError extends Error {
  constructor(message: string, public errors: Record<string, string[]>) {
    super(message);
    this.name = "ValidationError";
  }
}

export type User = {
  id: number;
  name: string;
  email: string;
  roles: Role[];
  // `/api/user` has always sent this; the normalizer used to drop it, which is why
  // ADR-40(6) calls adding it a gap in normalization rather than an API change. It is
  // REQUIRED, not optional: `normalizeUser` below is the only place in the project where a
  // `User` is constructed, so no call site has to be updated to carry it.
  department_id: number | null;
};

type RawUser = {
  id: number;
  name: string;
  email: string;
  roles: Array<{ name: string }>;
  department_id: number | null;
};

function normalizeUser(raw: RawUser): User {
  return {
    id: raw.id,
    name: raw.name,
    email: raw.email,
    roles: raw.roles.map((r) => r.name) as Role[],
    department_id: raw.department_id,
  };
}

function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

function setToken(token: string): void {
  if (typeof window === "undefined") return;
  localStorage.setItem(TOKEN_KEY, token);
}

function clearToken(): void {
  if (typeof window === "undefined") return;
  localStorage.removeItem(TOKEN_KEY);
}

/**
 * The locale the user is currently reading, taken from the `[locale]` path segment.
 *
 * That segment is the authoritative source here: `routing.ts` sets `localeDetection: false`,
 * so the `NEXT_LOCALE` cookie is not what decides which language the page rendered in. The
 * value is checked against `routing.locales` rather than forwarded as read — a path segment
 * is user input.
 *
 * Returns null on the server (no `window`) and on any path without a locale prefix; the
 * request then carries no `Accept-Language` and the backend answers in its configured
 * default (ADR-49).
 */
function currentLocale(): string | null {
  if (typeof window === "undefined") return null;

  const segment = window.location.pathname.split("/")[1];

  return (routing.locales as readonly string[]).includes(segment) ? segment : null;
}

async function request(path: string, options: RequestInit = {}): Promise<Response> {
  const token = getToken();
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body) headers.set("Content-Type", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);
  // `Accept-Language`, not a header of our own: it is CORS-safelisted, so it needs no
  // preflight and no change to the backend's `config/cors.php` (ADR-49).
  const locale = currentLocale();
  if (locale) headers.set("Accept-Language", locale);

  return fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });
}

export async function login(email: string, password: string): Promise<User> {
  const response = await request("/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });

  if (response.status === 422) {
    const data = await response.json();
    const firstError = Object.values(data.errors ?? {})[0];
    const message = Array.isArray(firstError) ? firstError[0] : "Validation failed.";
    throw new Error(message);
  }

  if (!response.ok) {
    throw new Error("Unable to log in. Please try again.");
  }

  const data = await response.json();
  setToken(data.token);
  return normalizeUser(data.user);
}

export async function logout(): Promise<void> {
  try {
    await request("/logout", { method: "POST" });
  } finally {
    clearToken();
  }
}

export async function getCurrentUser(): Promise<User | null> {
  if (!getToken()) return null;

  const response = await request("/user");
  if (!response.ok) return null;

  return normalizeUser(await response.json());
}

export type UpdateCurrentUserPasswordPayload = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

/**
 * Self-service password change (ADR-40(1)): `PUT /user/password`, with no id in the path
 * because the target is the token's own user. Deliberately NOT `updateUserPassword`, which
 * is the owner/pm-only admin reset, takes a target id and sends no current password.
 */
export async function updateCurrentUserPassword(
  payload: UpdateCurrentUserPasswordPayload,
): Promise<void> {
  const response = await request("/user/password", {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  // A wrong current password arrives here as a 422 on `current_password`, the same channel
  // every other form uses for field errors.
  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  // Success is 204 with an EMPTY body, so nothing is parsed on this path — the shape
  // `logout`, `deleteCategory` and `updateUserPassword` already use for void endpoints.
  if (!response.ok) {
    throw new Error("Unable to change the password. Please try again.");
  }
}

export type ToolsPage = {
  tools: Tool[];
  currentPage: number;
  lastPage: number;
  total: number;
};

export type ToolsQuery = {
  search?: string;
  category?: string;
  role?: string;
  department?: string;
  page?: number;
};

type ToolsEnvelope = {
  data: Tool[];
  current_page: number;
  last_page: number;
  total: number;
};

export async function getTools(query: ToolsQuery = {}): Promise<ToolsPage> {
  const params = new URLSearchParams();
  if (query.search) params.set("search", query.search);
  if (query.category) params.set("category", query.category);
  if (query.role) params.set("role", query.role);
  if (query.department) params.set("department", query.department);
  if (query.page) params.set("page", String(query.page));

  const queryString = params.toString();
  const response = await request(`/tools${queryString ? `?${queryString}` : ""}`);

  if (!response.ok) {
    throw new Error("Unable to load tools. Please try again.");
  }

  const data: ToolsEnvelope = await response.json();
  return {
    tools: data.data,
    currentPage: data.current_page,
    lastPage: data.last_page,
    total: data.total,
  };
}

export async function getCategories(): Promise<CategoryWithUsage[]> {
  const response = await request("/categories");

  if (!response.ok) {
    throw new Error("Unable to load categories. Please try again.");
  }

  return response.json();
}

/**
 * A category name is a translation map (ADR-27). `bg` is required — it is the fallback —
 * and the other two are omitted entirely when the admin leaves them blank: the backend
 * rejects a present-but-empty translation, so "" must never be sent.
 */
export type CategoryNamePayload = { bg: string; en?: string; fr?: string };

export type CreateCategoryPayload = {
  name: CategoryNamePayload;
  slug: string;
};

/**
 * Deliberately has NO `slug`. The slug is immutable after creation (ADR-28) and the
 * backend answers 422 if it is present at all, so the type removes the possibility of
 * sending it rather than relying on a disabled input.
 */
export type UpdateCategoryPayload = {
  name: CategoryNamePayload;
};

export async function createCategory(payload: CreateCategoryPayload): Promise<Category> {
  const response = await request("/categories", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to save the category. Please try again.");
  }

  return response.json();
}

export async function updateCategory(
  id: number,
  payload: UpdateCategoryPayload,
): Promise<Category> {
  const response = await request(`/categories/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to save the category. Please try again.");
  }

  return response.json();
}

export async function deleteCategory(id: number): Promise<void> {
  const response = await request(`/categories/${id}`, { method: "DELETE" });

  // 422 means the category is still used by tools (ADR-28). The UI disables the button
  // in that case, so reaching here means the count was stale — a concurrent edit.
  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to delete the category. Please try again.");
  }
}

export async function getRoles(): Promise<RoleOption[]> {
  const response = await request("/roles");

  if (!response.ok) {
    throw new Error("Unable to load roles. Please try again.");
  }

  return response.json();
}

export async function getDepartments(): Promise<Department[]> {
  const response = await request("/departments");
  if (!response.ok) {
    throw new Error("Unable to load departments. Please try again.");
  }
  return response.json();
}

export type ToolPayload = {
  name: string;
  description: string;
  url: string;
  documentation_url: string | null;
  video_url: string | null;
  difficulty: Difficulty | null;
  // Optional because a non-publisher omits it entirely (ADR-35): the backend forces a draft
  // on create and leaves the stored status alone on update.
  status?: ToolStatus;
  category_ids: number[];
  role_ids: number[];
  department_ids: number[];
};

export async function createTool(payload: ToolPayload): Promise<Tool> {
  const response = await request("/tools", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to save the tool. Please try again.");
  }

  return response.json();
}

export async function getTool(id: number): Promise<Tool> {
  const response = await request(`/tools/${id}`);

  if (!response.ok) {
    throw new Error("Unable to load the tool. Please try again.");
  }

  return response.json();
}

export async function updateTool(id: number, payload: ToolPayload): Promise<Tool> {
  const response = await request(`/tools/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to save the tool. Please try again.");
  }

  return response.json();
}

/**
 * Success is 204 with an EMPTY body, so nothing is parsed on this path — the same shape
 * `logout`, `deleteCategory` and `updateCurrentUserPassword` already use for void endpoints.
 * Unlike `deleteCategory`, there is no 422 branch: `ToolController::destroy` runs no
 * validation, so the only failure statuses are 401, 403, 404 and 5xx.
 */
export async function deleteTool(id: number): Promise<void> {
  const response = await request(`/tools/${id}`, { method: "DELETE" });

  if (!response.ok) {
    throw new Error("Unable to delete the tool. Please try again.");
  }
}

export async function getUsers(): Promise<AdminUser[]> {
  const response = await request("/users");

  if (!response.ok) {
    throw new Error("Unable to load users. Please try again.");
  }

  return response.json();
}

export type CreateUserPayload = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role_ids: number[];
  department_id: number | null;
};

/**
 * No `role_ids` and no `password`: those are separate endpoints (`/roles`, `/password`),
 * and sending them here is a 422 (`prohibited`) by design. `department_id` is
 * required-but-nullable rather than optional: an OMITTED `department_id` on this endpoint
 * clears the stored department, so the type forces every caller to send an explicit value
 * — the same reasoning as `ToolPayload`.
 */
export type UpdateUserPayload = {
  name: string;
  email: string;
  department_id: number | null;
};

export type UpdateUserRolesPayload = { role_ids: number[] };

export type UpdateUserPasswordPayload = { password: string; password_confirmation: string };

export async function createUser(payload: CreateUserPayload): Promise<AdminUser> {
  const response = await request("/users", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to save the user. Please try again.");
  }

  return response.json();
}

export async function updateUser(id: number, payload: UpdateUserPayload): Promise<AdminUser> {
  const response = await request(`/users/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to save the user. Please try again.");
  }

  return response.json();
}

export async function updateUserRoles(
  id: number,
  payload: UpdateUserRolesPayload,
): Promise<AdminUser> {
  const response = await request(`/users/${id}/roles`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to save the roles. Please try again.");
  }

  return response.json();
}

export async function updateUserPassword(
  id: number,
  payload: UpdateUserPasswordPayload,
): Promise<void> {
  const response = await request(`/users/${id}/password`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to change the password. Please try again.");
  }
}

export async function activateUser(id: number): Promise<AdminUser> {
  const response = await request(`/users/${id}/activate`, { method: "POST" });

  if (!response.ok) {
    throw new Error("Unable to activate the user. Please try again.");
  }

  return response.json();
}

export async function deactivateUser(id: number): Promise<AdminUser> {
  const response = await request(`/users/${id}/deactivate`, { method: "POST" });

  if (!response.ok) {
    throw new Error("Unable to deactivate the user. Please try again.");
  }

  return response.json();
}

export type Comment = {
  id: number;
  body: string;
  created_at: string;
  // NULLABLE on purpose: `user_id` is nullOnDelete (ADR-47), so removing a user leaves the
  // comment standing with no author rather than deleting words other people replied to.
  user: { id: number; name: string } | null;
};

/**
 * The same envelope shape as ToolsPage: the API returns Laravel's paginator, and the
 * snake_case keys are renamed here so no component has to know that.
 */
export type CommentsPage = {
  comments: Comment[];
  currentPage: number;
  lastPage: number;
  total: number;
};

type CommentsEnvelope = {
  data: Comment[];
  current_page: number;
  last_page: number;
  total: number;
};

export async function getComments(toolId: number, page?: number): Promise<CommentsPage> {
  const query = page ? `?page=${page}` : "";
  const response = await request(`/tools/${toolId}/comments${query}`);

  if (!response.ok) {
    throw new Error("Unable to load comments. Please try again.");
  }

  const data: CommentsEnvelope = await response.json();

  return {
    comments: data.data,
    currentPage: data.current_page,
    lastPage: data.last_page,
    total: data.total,
  };
}

export async function createComment(toolId: number, body: string): Promise<Comment> {
  const response = await request(`/tools/${toolId}/comments`, {
    method: "POST",
    body: JSON.stringify({ body }),
  });

  // The 2000-character limit and the empty-body rule are enforced by the backend, so both
  // arrive here as a 422 and surface under the field — the shape every other form uses.
  if (response.status === 422) {
    const data = await response.json();
    throw new ValidationError(data.message ?? "Validation failed.", data.errors ?? {});
  }

  if (!response.ok) {
    throw new Error("Unable to publish the comment. Please try again.");
  }

  return response.json();
}
