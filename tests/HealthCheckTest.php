<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthCheckTest extends WebTestCase
{
    public function testLiveEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health/live');

        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString('{"status":"ok"}', $client->getResponse()->getContent());
    }
}
