<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Project;
use App\Models\Simulation;
use App\Services\Assistant\AssistantManager;
use App\Services\Assistant\ClaudeAssistant;
use App\Services\Assistant\WorkshopAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * درایور مدل زبانی دستیار.
 *
 * هیچ‌کدام از این آزمون‌ها به شبکه نمی‌روند؛ همه پاسخ‌ها با Http::fake ساخته
 * می‌شوند. آنچه سنجیده می‌شود: شکل درخواست، استفاده از پاسخ درست، برگشت امن به
 * قواعد در خطا و کندی، خاموش بودن کامل درایور در حالت پیش‌فرض، و پاک شدن
 * اطلاعات شخصی مشتری پیش از رفتن داده‌ها به بیرون.
 */
class AssistantDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.assistant', [
            'driver' => 'claude',
            'key' => 'test-key',
            'base_url' => 'https://api.anthropic.com',
            'model' => 'claude-opus-5',
            'version' => '2023-06-01',
            'max_tokens' => 1024,
            'timeout' => 20,
        ]);
    }

    public function test_the_rules_driver_is_the_default_and_never_calls_the_network(): void
    {
        config()->set('services.assistant.driver', 'rules');

        $this->actingAsWorkshopUser();
        Http::fake();

        $driver = app(AssistantManager::class)->driver();

        $this->assertInstanceOf(WorkshopAssistant::class, $driver);
        $this->assertSame('rules', $driver->driverName());

        $this->post(route('assistant.ask'), [
            'question' => 'این پارچه برای این مدل مناسب است؟',
            'fabric_id' => $this->fabric()->id,
        ])->assertRedirect();

        Http::assertNothingSent();

        $answer = session('assistant.answer');

        $this->assertSame('rules', $answer['source']);
        $this->assertSame(WorkshopAssistant::SOURCE_LABEL, $answer['source_label']);
    }

    public function test_a_good_model_answer_is_used_and_the_request_has_the_right_shape(): void
    {
        $this->actingAsWorkshopUser();

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_01',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-opus-5',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'این کتان برای پیراهن مناسب است؛ آزادی چهار سانتی‌متر بدهید.']],
                'usage' => ['input_tokens' => 800, 'output_tokens' => 60],
            ]),
        ]);

        $fabric = $this->fabric();
        $garmentType = GarmentType::factory()->create(['name_fa' => 'پیراهن مردانه', 'category' => 'top']);

        $answer = app(AssistantManager::class)->driver()->ask('این پارچه مناسب است؟', $fabric, $garmentType);

        $this->assertSame('claude', $answer['source']);
        $this->assertSame(ClaudeAssistant::SOURCE_LABEL, $answer['source_label']);
        $this->assertSame('این کتان برای پیراهن مناسب است؛ آزادی چهار سانتی‌متر بدهید.', $answer['headline']);
        $this->assertArrayNotHasKey('fallback_reason', $answer);

        // نکته‌ها و استدلال‌های قاعده‌محور سر جای خودشان می‌مانند
        $this->assertSame('fabric_fit', $answer['topic']);
        $this->assertNotEmpty($answer['reasons']);

        Http::assertSent(function (Request $request) use ($fabric, $garmentType) {
            $body = $request->data();

            $this->assertSame('https://api.anthropic.com/v1/messages', $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame('test-key', $request->header('x-api-key')[0]);
            $this->assertSame('2023-06-01', $request->header('anthropic-version')[0]);

            $this->assertSame('claude-opus-5', $body['model']);
            $this->assertSame(1024, $body['max_tokens']);
            $this->assertStringContainsString('فارسی', $body['system']);
            $this->assertStringContainsString('کوتاه', $body['system']);

            $this->assertCount(1, $body['messages']);
            $this->assertSame('user', $body['messages'][0]['role']);

            $text = $body['messages'][0]['content'][0]['text'];

            $this->assertStringContainsString('این پارچه مناسب است؟', $text);
            $this->assertStringContainsString($fabric->name, $text);
            $this->assertStringContainsString($garmentType->name_fa, $text);
            $this->assertStringContainsString('سازگاری پارچه با مدل', $text);

            return true;
        });
    }

    public function test_a_failing_service_falls_back_to_the_rules_and_says_so(): void
    {
        $this->actingAsWorkshopUser();

        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529)]);

        $answer = app(AssistantManager::class)->driver()->ask('چند متر پارچه لازم است؟', $this->fabric());

        $this->assertSame('rules', $answer['source']);
        $this->assertSame(WorkshopAssistant::SOURCE_LABEL, $answer['source_label']);
        $this->assertStringContainsString('خطا', $answer['fallback_reason']);
        $this->assertNotSame('', $answer['headline']);
        $this->assertTrue(collect($answer['reasons'])->contains(fn (array $reason) => $reason['label'] === 'مدل زبانی در دسترس نبود'));
    }

    public function test_a_timeout_falls_back_instead_of_breaking_the_page(): void
    {
        $this->actingAsWorkshopUser();

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $fabric = $this->fabric();

        $answer = app(AssistantManager::class)->driver()->ask('هنگام برش مراقب چه چیزی باشم؟', $fabric);

        $this->assertSame('rules', $answer['source']);
        $this->assertStringContainsString('در دسترس نبود', $answer['fallback_reason']);

        // صفحه هم باید سالم بالا بیاید
        $this->post(route('assistant.ask'), [
            'question' => 'هنگام برش مراقب چه چیزی باشم؟',
            'fabric_id' => $fabric->id,
        ])->assertRedirect(route('assistant.index'));

        $this->get(route('assistant.index'))
            ->assertOk()
            ->assertSee('این پاسخ از قواعد سامانه آمده است');
    }

    public function test_a_refusal_or_empty_answer_falls_back(): void
    {
        $this->actingAsWorkshopUser();

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'refusal',
                'content' => [],
            ]),
        ]);

        $answer = app(AssistantManager::class)->driver()->ask('آستر لازم دارد؟', $this->fabric());

        $this->assertSame('rules', $answer['source']);
        $this->assertStringContainsString('خالی', $answer['fallback_reason']);
    }

    public function test_a_missing_key_never_reaches_the_network(): void
    {
        config()->set('services.assistant.key', null);

        $this->actingAsWorkshopUser();
        Http::fake();

        $answer = app(AssistantManager::class)->driver()->ask('آستر لازم دارد؟', $this->fabric());

        Http::assertNothingSent();

        $this->assertSame('rules', $answer['source']);
        $this->assertStringContainsString('کلید', $answer['fallback_reason']);
    }

    public function test_customer_names_and_phone_numbers_are_stripped_before_sending(): void
    {
        $this->actingAsWorkshopUser();

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'پاسخ کوتاه.']],
            ]),
        ]);

        $customer = Customer::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'نازنین رستمی',
            'phone' => '09121234567',
        ]);

        $fabric = $this->fabric();
        $garmentType = GarmentType::factory()->create(['name_fa' => 'پیراهن', 'category' => 'top']);

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'garment_type_id' => $garmentType->id,
            'fabric_id' => $fabric->id,
            'name' => 'پیراهن نازنین رستمی — تماس ۰۹۱۲۱۲۳۴۵۶۷',
        ]);

        Simulation::create([
            'workshop_id' => $this->workshop()->id,
            'project_id' => $project->id,
            'fabric_id' => $fabric->id,
            'pose' => 'stand',
            'fit_score' => 78,
            'warnings' => ['دور سینه برای نازنین رستمی کمی تنگ است'],
        ]);

        app(AssistantManager::class)->driver()->ask(
            'این پارچه مناسب است؟',
            null,
            null,
            $project->fresh(['fabric.fabricType', 'garmentType', 'customer', 'latestSimulation']),
        );

        Http::assertSent(function (Request $request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString('نازنین رستمی', $body);
            $this->assertStringNotContainsString('نازنین', $body);
            $this->assertStringNotContainsString('رستمی', $body);
            $this->assertStringNotContainsString('09121234567', $body);
            $this->assertStringNotContainsString('۰۹۱۲۱۲۳۴۵۶۷', $body);

            // ولی داده‌های کار باید رفته باشند
            $this->assertStringContainsString('پارچه', $body);

            return true;
        });
    }

    public function test_the_page_shows_which_answer_the_user_got(): void
    {
        $this->actingAsWorkshopUser();

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'برای این کار حدود دو متر پارچه بگیرید.']],
            ]),
        ]);

        $this->post(route('assistant.ask'), [
            'question' => 'چند متر پارچه لازم است؟',
            'fabric_id' => $this->fabric()->id,
            'garment_type_id' => GarmentType::factory()->create(['name_fa' => 'مانتو', 'category' => 'outer'])->id,
        ])->assertRedirect();

        $this->get(route('assistant.index'))
            ->assertOk()
            ->assertSee('این پاسخ با کمک مدل زبانی تهیه شده است')
            ->assertSee('برای این کار حدود دو متر پارچه بگیرید.');
    }

    protected function fabric(): Fabric
    {
        return Fabric::factory()->for(FabricType::factory()->create(['name_fa' => 'کتان']))->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'کتان ایتالیایی',
            'gsm' => null,
        ]);
    }
}
