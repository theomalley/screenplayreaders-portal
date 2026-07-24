<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * All 10 InvoiceController actions use the same isAdminOrEditor() rule. Several
 * also have a secondary precondition (invoice_type === 'pdf', google_doc_id set,
 * status === 'draft') that isn't authorization — the fixtures below satisfy those
 * so a "not 403" result genuinely reflects the auth check passing, not a
 * precondition short-circuit. All the code paths this hits are wrapped in
 * try/catch around external services (Google Docs/Stripe/MailerSend) and fail
 * gracefully to a redirect-with-errors, so no mocking is needed.
 */
class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(): Client
    {
        return Client::create(['name' => 'Test Client', 'code' => 'TC-' . random_int(100000, 999999)]);
    }

    private function makeInvoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id'      => $this->makeClient()->id,
            'invoice_number' => 'INV-' . random_int(100000, 999999),
            'amount'         => 100,
            'status'         => 'sent',
            'invoice_type'   => 'pdf',
            'google_doc_id'  => 'fake-doc-id',
        ], $overrides));
    }

    public function test_admin_and_editor_are_not_forbidden_from_any_invoice_action(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        foreach ([$admin, $editor] as $user) {
            $this->assertNotEquals(403, $this->actingAs($user)->get('/invoicing')->getStatusCode());
            $this->assertNotEquals(403, $this->actingAs($user)->get('/invoicing/create')->getStatusCode());

            $client = $this->makeClient();
            $this->assertNotEquals(403, $this->actingAs($user)->post('/invoicing', [
                'client_id' => $client->id,
                'items'     => [['description' => 'Item', 'amount' => 10]],
            ])->getStatusCode());

            $invoice = $this->makeInvoice();
            $this->assertNotEquals(403, $this->actingAs($user)->get("/invoices/{$invoice->id}/edit")->getStatusCode());
            $this->assertNotEquals(403, $this->actingAs($user)->patch("/invoices/{$invoice->id}", [
                'items' => [['description' => 'Item', 'amount' => 10]],
            ])->getStatusCode());
            $this->assertNotEquals(403, $this->actingAs($user)->get("/invoices/{$invoice->id}/pdf")->getStatusCode());
            $this->assertNotEquals(403, $this->actingAs($user)->post("/invoices/{$invoice->id}/resend")->getStatusCode());

            $draft = $this->makeInvoice(['status' => 'draft']);
            $this->assertNotEquals(403, $this->actingAs($user)->post("/invoices/{$draft->id}/send")->getStatusCode());

            $this->assertNotEquals(403, $this->actingAs($user)->post("/invoices/{$invoice->id}/mark-paid")->getStatusCode());
            $this->assertNotEquals(403, $this->actingAs($user)->post("/invoices/{$invoice->fresh()->id}/mark-outstanding")->getStatusCode());
            $this->assertNotEquals(403, $this->actingAs($user)->post("/invoices/{$invoice->id}/void")->getStatusCode());

            $paid = $this->makeInvoice(['status' => 'paid']);
            $this->assertNotEquals(403, $this->actingAs($user)->delete("/invoices/{$paid->id}")->getStatusCode());
        }
    }

    public function test_reader_is_forbidden_from_every_invoice_action(): void
    {
        $reader  = User::factory()->create(['role' => 'reader']);
        $client  = $this->makeClient();
        $invoice = $this->makeInvoice();

        $this->actingAs($reader)->get('/invoicing')->assertForbidden();
        $this->actingAs($reader)->get('/invoicing/create')->assertForbidden();
        $this->actingAs($reader)->post('/invoicing', ['client_id' => $client->id])->assertForbidden();
        $this->actingAs($reader)->get("/invoices/{$invoice->id}/edit")->assertForbidden();
        $this->actingAs($reader)->patch("/invoices/{$invoice->id}", [])->assertForbidden();
        $this->actingAs($reader)->get("/invoices/{$invoice->id}/pdf")->assertForbidden();
        $this->actingAs($reader)->post("/invoices/{$invoice->id}/resend")->assertForbidden();
        $this->actingAs($reader)->post("/invoices/{$invoice->id}/send")->assertForbidden();
        $this->actingAs($reader)->post("/invoices/{$invoice->id}/mark-paid")->assertForbidden();
        $this->actingAs($reader)->post("/invoices/{$invoice->id}/mark-outstanding")->assertForbidden();
        $this->actingAs($reader)->post("/invoices/{$invoice->id}/void")->assertForbidden();
        $this->actingAs($reader)->delete("/invoices/{$invoice->id}")->assertForbidden();
    }
}
