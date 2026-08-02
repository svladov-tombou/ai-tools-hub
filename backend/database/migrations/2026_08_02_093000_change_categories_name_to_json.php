<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Turn `name` into a translation map, preserving the existing Bulgarian names.
     *
     * Raw statements are used deliberately: MySQL cannot convert a VARCHAR to JSON
     * while rewriting its contents in a single Schema::table() call, and the project
     * is MySQL-only (ADR-5), tests included (phpunit.xml overrides the database, not
     * the connection).
     */
    public function up(): void
    {
        // MySQL cannot place a UNIQUE index on a JSON column. `slug` stays unique and
        // remains the real identifier (ADR-26), so nothing depends on this index.
        DB::statement('ALTER TABLE categories DROP INDEX categories_name_unique');

        // Widen first: the JSON wrapper adds ~10 characters, which would truncate a
        // name close to the VARCHAR(255) ceiling.
        DB::statement('ALTER TABLE categories MODIFY name TEXT NOT NULL');

        DB::statement(<<<'SQL'
            UPDATE categories SET name = JSON_OBJECT('bg', name)
        SQL);

        DB::statement('ALTER TABLE categories MODIFY name JSON NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE categories ADD COLUMN name_bg VARCHAR(255) NULL AFTER name');

        DB::statement(<<<'SQL'
            UPDATE categories SET name_bg = JSON_UNQUOTE(JSON_EXTRACT(name, '$.bg'))
        SQL);

        DB::statement('ALTER TABLE categories DROP COLUMN name');
        DB::statement('ALTER TABLE categories CHANGE name_bg name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE categories ADD UNIQUE categories_name_unique (name)');
    }
};
