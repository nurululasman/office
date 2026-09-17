<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE quotations SET sender_id = created_by WHERE sender_id IS NULL');

        DB::statement("UPDATE quotations SET sender_name = (SELECT name FROM users WHERE users.id = quotations.sender_id) WHERE sender_id IS NOT NULL AND sender_name IN (SELECT username FROM users WHERE username IS NOT NULL AND username != '') AND EXISTS (SELECT 1 FROM users WHERE users.id = quotations.sender_id AND users.name IS NOT NULL AND users.name != '')");
    }

    public function down(): void
    {
        // Data migration only
    }
};
