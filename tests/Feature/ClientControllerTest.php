<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(): Client
    {
        return Client::create([
            'name' => 'Test Client',
            'code' => 'TC-' . random_int(100000, 999999),
        ]);
    }

    public function test_admin_and_editor_can_view_create_store_show_and_edit(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $client = $this->makeClient();

        $this->actingAs($admin)->get('/clients')->assertOk();
        $this->actingAs($admin)->get('/clients/create')->assertOk();
        $this->actingAs($admin)->get("/clients/{$client->id}")->assertOk();
        $this->actingAs($admin)->get("/clients/{$client->id}/edit")->assertOk();

        $this->actingAs($editor)->get('/clients')->assertOk();
        $this->actingAs($editor)->post('/clients', ['name' => 'New Client', 'code' => 'NC-1'])->assertRedirect();
        $this->actingAs($editor)->patch("/clients/{$client->id}", ['name' => 'Updated', 'code' => $client->code])->assertRedirect();
    }

    public function test_reader_cannot_view_create_store_show_edit_or_update(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $client = $this->makeClient();

        $this->actingAs($reader)->get('/clients')->assertForbidden();
        $this->actingAs($reader)->get('/clients/create')->assertForbidden();
        $this->actingAs($reader)->post('/clients', ['name' => 'X', 'code' => 'X'])->assertForbidden();
        $this->actingAs($reader)->get("/clients/{$client->id}")->assertForbidden();
        $this->actingAs($reader)->get("/clients/{$client->id}/edit")->assertForbidden();
        $this->actingAs($reader)->patch("/clients/{$client->id}", ['name' => 'X', 'code' => $client->code])->assertForbidden();
    }

    public function test_only_admin_can_delete_a_client(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $c1     = $this->makeClient();
        $c2     = $this->makeClient();

        $this->actingAs($editor)->delete("/clients/{$c1->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/clients/{$c2->id}")->assertRedirect();
        $this->assertDatabaseMissing('clients', ['id' => $c2->id]);
    }
}
