<?php

namespace Tests\Feature;

use App\Services\WherebyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WherebyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whereby.api_key' => 'test-api-key']);
    }

    public function test_create_room_sends_whereby_hosted_cloud_recording_and_parses_response(): void
    {
        Http::fake([
            'api.whereby.dev/v1/meetings' => Http::response([
                'meetingId' => 'mtg-123',
                'roomName'  => '/room-abc',
                'roomUrl'   => 'https://subdomain.whereby.com/room-abc',
                'startDate' => '2026-12-01T00:00:00Z',
                'endDate'   => '2026-12-01T01:00:00Z',
            ], 201),
        ]);

        $result = (new WherebyService())->createRoom(Carbon::parse('2026-12-01T01:00:00Z'));

        $this->assertSame('mtg-123', $result['meetingId']);
        $this->assertSame('/room-abc', $result['roomName']);
        $this->assertSame('https://subdomain.whereby.com/room-abc', $result['roomUrl']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.whereby.dev/v1/meetings'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request['recording']['type'] === 'cloud'
                && $request['recording']['destination']['provider'] === 'whereby'
                && $request['recording']['startTrigger'] === 'automatic-2nd-participant';
        });
    }

    public function test_get_recording_access_link_parses_response(): void
    {
        Http::fake([
            'api.whereby.dev/v1/recordings/*' => Http::response([
                'accessLink' => 'https://s3.example.com/signed-url',
                'expires'    => 1740819600000,
            ], 200),
        ]);

        $result = (new WherebyService())->getRecordingAccessLink('rec-123');

        $this->assertSame('https://s3.example.com/signed-url', $result['accessLink']);
        $this->assertSame(1740819600000, $result['expires']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/recordings/rec-123/access-link')
                && $request['validForSeconds'] === 3600;
        });
    }
}
