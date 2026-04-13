<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\MemberModule;
use App\Models\MemberSection;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class MemberBuilderStudentProgressTest extends TestCase
{
    public function test_member_builder_payload_includes_student_progress_for_enrolled_users(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $owner = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $product = $this->createTestProduct([
            'type' => Product::TYPE_AREA_MEMBROS,
            'checkout_slug' => 'mbprog'.substr(uniqid('', true), -8),
            'slug' => 'p-'.substr(uniqid('', true), -8),
        ]);

        $section = MemberSection::create([
            'product_id' => $product->id,
            'title' => 'Seção',
            'position' => 1,
            'cover_mode' => 'vertical',
            'section_type' => 'courses',
        ]);

        $module = MemberModule::create([
            'member_section_id' => $section->id,
            'product_id' => $product->id,
            'title' => 'Módulo 1',
            'position' => 1,
        ]);

        $lessonA = MemberLesson::create([
            'member_module_id' => $module->id,
            'product_id' => $product->id,
            'title' => 'Aula A',
            'position' => 1,
            'type' => MemberLesson::TYPE_TEXT,
        ]);

        $lessonB = MemberLesson::create([
            'member_module_id' => $module->id,
            'product_id' => $product->id,
            'title' => 'Aula B',
            'position' => 2,
            'type' => MemberLesson::TYPE_TEXT,
        ]);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => 1,
        ]);
        $product->users()->attach($aluno->id);

        MemberLessonProgress::create([
            'user_id' => $aluno->id,
            'member_lesson_id' => $lessonA->id,
            'product_id' => $product->id,
            'completed_at' => now(),
            'progress_percent' => 100,
        ]);

        $response = $this->actingAs($owner)->get(route('member-builder.index', $product));

        $response->assertStatus(200);
        $response->assertViewHas('produto', function (array $payload) use ($aluno) {
            if (($payload['total_lessons'] ?? null) !== 2) {
                return false;
            }
            $rows = $payload['student_progress'] ?? [];
            if (count($rows) !== 1) {
                return false;
            }
            $row = $rows[0];
            if ((int) ($row['id'] ?? 0) !== (int) $aluno->id) {
                return false;
            }
            if (($row['completed_count'] ?? null) !== 1) {
                return false;
            }
            if (($row['total_lessons'] ?? null) !== 2) {
                return false;
            }
            if (($row['percent'] ?? null) !== 50) {
                return false;
            }

            return true;
        });
    }
}
