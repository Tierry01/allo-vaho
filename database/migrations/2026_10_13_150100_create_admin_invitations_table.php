<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('role', 50);
            // SHA-256 of the token sent by e-mail: the raw token is never stored.
            $table->char('token_hash', 64)->unique();
            // Null when the invitation was issued from the server console.
            $table->foreignId('invited_by_id')->nullable()->constrained('admins');
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        // At most one open invitation per e-mail address.
        DB::statement(
            'create unique index admin_invitations_open_email_unique on admin_invitations (email) '
            .'where accepted_at is null and revoked_at is null'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
    }
};
