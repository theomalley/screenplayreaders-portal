<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcVideoConsultingPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(): User
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        return $editor;
    }

    private function makeVideoConsultingAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'      => 'TEST-VC-' . random_int(100000, 999999),
            'vendor'            => 'sr',
            'assignment_type'   => Assignment::TYPE_VIDEO_CONSULTING,
            'script_title'      => '30 Minutes Video Consulting Call',
            'writer_name'       => 'N/A (test booking)',
            'pay_rate'          => 50,
            'status'            => Assignment::STATUS_QC,
            'submitted_at'      => now(),
            'is_test'           => true,
        ], $overrides));
    }

    private function denyEditorVideoConsultingQc(): void
    {
        Setting::setValue(Permission::settingKey('qc.video_consulting', 'editor'), '0');
    }

    public function test_editor_can_view_video_consulting_qc_by_default(): void
    {
        $editor = $this->makeEditor();
        $assignment = $this->makeVideoConsultingAssignment();

        $this->actingAs($editor)->get("/qc/{$assignment->id}")->assertOk();
    }

    public function test_editor_is_blocked_from_video_consulting_qc_once_disabled(): void
    {
        $editor = $this->makeEditor();
        $this->denyEditorVideoConsultingQc();
        $assignment = $this->makeVideoConsultingAssignment();

        $this->actingAs($editor)->get("/qc/{$assignment->id}")->assertForbidden();
    }

    public function test_disabled_editor_can_still_qc_ordinary_coverage(): void
    {
        $editor = $this->makeEditor();
        $this->denyEditorVideoConsultingQc();
        $coverage = Assignment::create([
            'order_number'          => 'QC-COVERAGE-' . random_int(1000, 9999),
            'script_title'          => 'Test Script',
            'writer_name'           => 'Test Writer',
            'page_count'            => 100,
            'pay_rate'              => 50,
            'status'                => Assignment::STATUS_QC,
            'drive_coverage_doc_id' => 'doc-123',
            'submitted_at'          => now(),
            'is_test'               => true,
        ]);

        $this->actingAs($editor)->get("/qc/{$coverage->id}")->assertOk();
    }

    public function test_disabled_editors_index_excludes_video_consulting_but_keeps_coverage(): void
    {
        $editor = $this->makeEditor();
        $this->denyEditorVideoConsultingQc();
        $videoConsulting = $this->makeVideoConsultingAssignment();
        $coverage = Assignment::create([
            'order_number'          => 'QC-COVERAGE-' . random_int(1000, 9999),
            'script_title'          => 'Test Script',
            'writer_name'           => 'Test Writer',
            'page_count'            => 100,
            'pay_rate'              => 50,
            'status'                => Assignment::STATUS_QC,
            'drive_coverage_doc_id' => 'doc-123',
            'submitted_at'          => now(),
            'is_test'               => true,
        ]);

        $response = $this->actingAs($editor)->get('/qc');

        $response->assertOk();
        $ids = $response->viewData('assignments')->pluck('id');
        $this->assertTrue($ids->contains($coverage->id));
        $this->assertFalse($ids->contains($videoConsulting->id));
    }

    public function test_disabled_editor_cannot_approve_or_save_transcript_for_video_consulting(): void
    {
        $editor = $this->makeEditor();
        $this->denyEditorVideoConsultingQc();
        $assignment = $this->makeVideoConsultingAssignment();

        $this->actingAs($editor)->post("/qc/{$assignment->id}/approve")->assertForbidden();
        $this->actingAs($editor)->post("/qc/{$assignment->id}/transcript", ['transcript' => 'hello'])->assertForbidden();
    }

    public function test_admin_is_never_blocked_regardless_of_the_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->denyEditorVideoConsultingQc();
        $assignment = $this->makeVideoConsultingAssignment();

        $this->actingAs($admin)->get("/qc/{$assignment->id}")->assertOk();
    }
}
