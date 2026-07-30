import type { Role } from "@/types";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost/api";
const TOKEN_KEY = "auth_token";

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
