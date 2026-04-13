<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_turma_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_turma_id')->constrained('member_turmas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['member_turma_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_turma_user');
    }
};
