<?php

namespace App\Tests\Controller\Profile;

use App\Entity\User;
use App\Message\Profile\SendLinkEmailPasswordConfirmationMail;
use App\Tests\WebTestCase;
use Symfony\Component\Messenger\Transport\TransportInterface;

class LinkEmailPasswordControllerTest extends WebTestCase
{
    /** @var User */
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->doctrine->getRepository(User::class)->findOneBy(['nickname' => 'Ashuvidz']);
    }

    /**
     * @group functional
     * @group profile
     */
    public function testSuccess(): void
    {
        static::assertNull($this->user->getEmail());
        static::assertFalse($this->user->isEmailConfirmed());
        $this->logIn($this->user);
        $this->client->xmlHttpRequest('POST', '/api/profile/link-email-password', [
            'email' => 'ashuvidz@example.com',
            'password' => '123456',
        ], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        static::assertSame(204, $this->client->getResponse()->getStatusCode());
        static::assertSame('ashuvidz@example.com', $this->user->getEmail());
        static::assertFalse($this->user->isEmailConfirmed());

        /** @var TransportInterface $transport */
        $transport = static::$container->get('messenger.transport.sync');
        $envelopes = $transport->get();
        static::assertCount(1, $envelopes);
        static::assertInstanceOf(SendLinkEmailPasswordConfirmationMail::class, $envelopes[0]->getMessage());
        static::assertSame($this->user->getId()->toString(), $envelopes[0]->getMessage()->getUserId()->toString());
    }

    /**
     * @group functional
     * @group profile
     */
    public function testChangeEmailRequestBadValue(): void
    {
        $this->logIn($this->user);
        $this->client->xmlHttpRequest('POST', '/api/profile/link-email-password', [
            'email' => 'bad-email',
            'password' => '123',
        ], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        static::assertSame(400, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        static::assertSame('invalid_form', $json['error']);
        static::assertSame('This value is not a valid email address.', $json['formErrors']['violations'][0]['title']);
        static::assertSame("Some extra characters and you'll have the 6 required. ;-)", $json['formErrors']['violations'][1]['title']);
    }
}
