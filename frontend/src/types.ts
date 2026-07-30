export type Role = "owner" | "pm" | "manager" | "employee";

export type Category = { id: number; name: string; slug: string };

export type RoleOption = {
  id: number;
  name: Role;
  display_name: string;
  level: number;
};

export type Difficulty = "beginner" | "intermediate" | "advanced";

export type Tool = {
  id: number;
  name: string;
  description: string;
  url: string;
  documentation_url: string | null;
  video_url: string | null;
  difficulty: Difficulty | null;
  status: "draft" | "published";
  created_by: number | null;
  created_at: string;
  updated_at: string;
  categories: Category[];
  roles: RoleOption[];
  creator: { id: number; name: string; email: string } | null;
};
