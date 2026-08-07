<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            // A deleted tool takes its conversation with it: the comments are about THAT
            // tool and mean nothing without it.
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            // nullOnDelete, matching created_by on tools (ADR-11): a removed user leaves
            // an authorless comment rather than deleting discussion other people replied to.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            // The only way this table is ever read: one tool's comments, newest first.
            $table->index(['tool_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
