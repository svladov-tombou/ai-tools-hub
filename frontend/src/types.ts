export type Role = "owner" | "pm" | "manager" | "employee";

export type Category = { id: number; name: string; slug: string };

export type DepartmentSlug =
  | "marketing"
  | "accounting"
  | "it"
  | "projects"
  | "commercial"
  | "sales"
  | "network"
  | "production"
  | "customer_support"
  | "administration"
  | "tender"
  | "telesales";

export type Department = { id: number; name: string; slug: DepartmentSlug };

export type RoleOption = {
  id: number;
  name: Role;
  display_name: string;
  level: number;
};

export type Difficulty = "beginner" | "intermediate" | "advanced";

export type ToolStatus = "draft" | "published";

export type Tool = {
  id: number;
  name: string;
  description: string;
  url: string;
  documentation_url: string | null;
  video_url: string | null;
  difficulty: Difficulty | null;
  status: ToolStatus;
  created_by: number | null;
  created_at: string;
  updated_at: string;
  categories: Category[];
  roles: RoleOption[];
  departments: Department[];
  creator: { id: number; name: string; email: string } | null;
};
