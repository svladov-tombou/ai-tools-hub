import type {
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
};

type RawUser = {
  id: number;
  name: string;
  email: string;
  roles: Array<{ name: string }>;
};

function normalizeUser(raw: RawUser): User {
  return {
    id: raw.id,
    name: raw.name,
    email: raw.email,
    roles: raw.roles.map((r) => r.name) as Role[],
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

async function request(path: string, options: RequestInit = {}): Promise<Response> {
  const token = getToken();
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body) headers.set("Content-Type", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);

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
  status: ToolStatus;
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
