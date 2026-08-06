<?php

namespace Tests\Unit;

use App\Services\TajweedAnalysisService;
use ReflectionMethod;
use Tests\TestCase;

class TajweedAnalysisServiceTest extends TestCase
{
    public function test_unavailable_legacy_backend_is_failed_not_uncertain(): void
    {
        $method = new ReflectionMethod(TajweedAnalysisService::class, 'getUnavailableResponse');
        $method->setAccessible(true);
        $response = $method->invoke(
            app(TajweedAnalysisService::class),
            'izhar',
            'The configured backend is unavailable.'
        );

        $this->assertNull($response['correctness']);
        $this->assertSame('failed', $response['processing_status']);
        $this->assertSame('failed', $response['classification_status']);
        $this->assertSame(0, $response['confidence_score']);
    }
}
