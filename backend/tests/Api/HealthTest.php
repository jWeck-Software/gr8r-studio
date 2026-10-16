<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthTest extends WebTestCase
{
    public function testHealthAnswersOkWithoutSigningIn(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['status' => 'ok'], json_decode($client->getResponse()->getContent(), true));
    }
}
