<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->unique()->after('name');
            $table->string('role', 16)->default('user')->after('password');
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->timestamp('suspended_at')->nullable()->index();
            $table->string('suspension_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropIndex(['country']);
            $table->dropIndex(['suspended_at']);
            $table->dropColumn(['username', 'role', 'bio', 'avatar_path', 'country', 'city', 'date_of_birth', 'suspended_at', 'suspension_reason']);
        });
    }
};
