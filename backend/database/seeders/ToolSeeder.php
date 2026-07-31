<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use App\Models\Role;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Tool::query()->exists()) {
            return;
        }

        $createdBy = User::where('email', 'ivan@admin.local')->first()?->id;

        $tools = [
            [
                'name' => 'ChatGPT',
                'description' => 'A conversational AI assistant from OpenAI for writing, brainstorming, coding help, and general Q&A.',
                'url' => 'https://chat.openai.com',
                'documentation_url' => 'https://help.openai.com/en/collections/3742473-chatgpt',
                'video_url' => null,
                'difficulty' => 'beginner',
                'status' => 'published',
                'categories' => ['writing', 'productivity'],
                'roles' => ['owner', 'pm', 'manager', 'employee'],
                'departments' => ['marketing', 'accounting', 'it', 'projects', 'commercial', 'sales', 'network', 'production', 'customer_support', 'administration', 'tender', 'telesales'],
            ],
            [
                'name' => 'Claude',
                'description' => "Anthropic's AI assistant, strong at long-context reasoning, writing, and coding tasks.",
                'url' => 'https://claude.ai',
                'documentation_url' => 'https://docs.anthropic.com',
                'video_url' => null,
                'difficulty' => 'beginner',
                'status' => 'published',
                'categories' => ['writing', 'code-assistants'],
                'roles' => ['owner', 'pm', 'employee'],
                'departments' => ['marketing', 'accounting', 'it', 'projects', 'commercial', 'sales', 'network', 'production', 'customer_support', 'administration', 'tender', 'telesales'],
            ],
            [
                'name' => 'GitHub Copilot',
                'description' => 'An AI pair programmer that suggests code completions and whole functions directly in the editor.',
                'url' => 'https://github.com/features/copilot',
                'documentation_url' => 'https://docs.github.com/en/copilot',
                'video_url' => 'https://www.youtube.com/watch?v=Fi3AJZZregI',
                'difficulty' => 'intermediate',
                'status' => 'published',
                'categories' => ['code-assistants'],
                'roles' => ['manager', 'employee'],
                'departments' => ['it', 'projects'],
            ],
            [
                'name' => 'Midjourney',
                'description' => 'An AI image generation tool known for producing highly artistic, stylized visuals from text prompts.',
                'url' => 'https://www.midjourney.com',
                'documentation_url' => 'https://docs.midjourney.com',
                'video_url' => null,
                'difficulty' => 'intermediate',
                'status' => 'published',
                'categories' => ['image-generation'],
                'roles' => ['pm', 'employee'],
                'departments' => ['marketing'],
            ],
            [
                'name' => 'Cursor',
                'description' => 'An AI-first code editor built on top of VS Code, with deep AI code generation and refactoring built in.',
                'url' => 'https://www.cursor.com',
                'documentation_url' => 'https://docs.cursor.com',
                'video_url' => null,
                'difficulty' => 'advanced',
                'status' => 'draft',
                'categories' => ['code-assistants', 'productivity'],
                'roles' => ['manager', 'employee'],
                'departments' => ['it', 'projects'],
            ],
            [
                'name' => 'Notion AI',
                'description' => 'An AI writing and summarization assistant built into Notion for drafting, editing, and organizing notes.',
                'url' => 'https://www.notion.so/product/ai',
                'documentation_url' => 'https://www.notion.so/help/notion-ai',
                'video_url' => null,
                'difficulty' => 'beginner',
                'status' => 'published',
                'categories' => ['productivity', 'writing'],
                'roles' => ['owner', 'pm', 'employee'],
                'departments' => ['marketing', 'projects', 'administration', 'accounting'],
            ],
            [
                'name' => 'Perplexity',
                'description' => 'An AI-powered answer engine that searches the web and returns cited, conversational answers.',
                'url' => 'https://www.perplexity.ai',
                'documentation_url' => 'https://docs.perplexity.ai',
                'video_url' => null,
                'difficulty' => 'beginner',
                'status' => 'published',
                'categories' => ['data-analytics', 'productivity'],
                'roles' => ['pm', 'manager', 'employee'],
                'departments' => ['marketing', 'tender', 'commercial'],
            ],
            [
                'name' => 'ElevenLabs',
                'description' => 'An AI voice generation platform for realistic text-to-speech, voice cloning, and dubbing.',
                'url' => 'https://elevenlabs.io',
                'documentation_url' => 'https://elevenlabs.io/docs',
                'video_url' => 'https://www.youtube.com/watch?v=TIsfFDpxDIA',
                'difficulty' => 'intermediate',
                'status' => 'published',
                'categories' => ['productivity'],
                'roles' => ['pm', 'employee'],
                'departments' => ['marketing', 'telesales'],
            ],
        ];

        foreach ($tools as $data) {
            $categorySlugs = $data['categories'];
            $roleNames = $data['roles'];
            $departmentSlugs = $data['departments'];
            unset($data['categories'], $data['roles'], $data['departments']);

            $tool = Tool::create($data);
            $tool->created_by = $createdBy;
            $tool->save();

            $categoryIds = Category::whereIn('slug', $categorySlugs)->pluck('id');
            $tool->categories()->sync($categoryIds);

            $roleIds = Role::whereIn('name', $roleNames)->pluck('id');
            $tool->roles()->sync($roleIds);

            $departmentIds = Department::whereIn('slug', $departmentSlugs)->pluck('id');
            $tool->departments()->sync($departmentIds);
        }
    }
}
