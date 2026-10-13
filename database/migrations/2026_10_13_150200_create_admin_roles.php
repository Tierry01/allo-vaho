<?php

use App\Domain\Admin\Enums\AdminRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The four back-office roles are reference data: every environment needs
     * them. No permission is created here.
     */
    public function up(): void
    {
        foreach (AdminRole::cases() as $role) {
            Role::findOrCreate($role->value, AdminRole::GUARD);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally left empty: removing a role would silently strip it from
     * the administrators who hold it.
     */
    public function down(): void
    {
        //
    }
};
