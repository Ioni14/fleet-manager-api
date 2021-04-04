<?php

namespace App\Tests\Controller\Spa;

use App\Tests\WebTestCase;

class HomeNumbersControllerTest extends WebTestCase
{
    /**
     * @group functional
     * @group api
     */
    public function testNumbers(): void
    {
        $this->client->xmlHttpRequest('GET', '/api/numbers', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        static::assertSame(200, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        static::assertArraySubset([
            'organizations' => 4,
            'users' => 44,
            'ships' => 12,
        ], $json);
    }
}
