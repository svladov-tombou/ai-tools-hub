import type { Role } from "@/types";

// Placeholder until real auth wiring (separate future phase). Shared by
// the navbar and the dashboard so there is exactly one fake user.
export const PLACEHOLDER_USER: { name: string; roles: Role[] } = {
  name: "Иван Иванов",
  roles: ["owner", "manager"],
};
