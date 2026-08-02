export type Role = "owner" | "pm" | "manager" | "employee";

/**
 * A name translated in the database rather than in a dictionary (ADR-27).
 * `bg` is required because it is the fallback; other languages may be missing.
 * `fr` is data only — French is not a selectable UI language yet.
 */
export type LocalizedName = { bg: string; en?: string; fr?: string };

export type Category = { id: number; name: LocalizedName; slug: string };

/**
 * What GET /api/categories returns (ADR-30). Kept separate from `Category` rather than
 * being an optional field, because the categories embedded in a tool carry no count —
 * an optional field would let a component read `tools_count` where it is never present.
 */
export type CategoryWithUsage = Category & { tools_count: number };

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
