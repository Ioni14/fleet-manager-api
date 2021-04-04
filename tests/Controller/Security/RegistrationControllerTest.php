<?php

namespace App\Tests\Controller\Security;

use App\Entity\User;
use App\Message\Registration\SendRegistrationConfirmationMail;
use App\Tests\WebTestCase;
use Symfony\Component\Messenger\Transport\TransportInterface;

class RegistrationControllerTest extends WebTestCase
{
    /**
     * @group functional
     * @group security
     */
    public function testRegistration(): void
    {
        $this->client->xmlHttpRequest('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'foobar@example.com',
            'password' => '123456',
        ]));

        static::assertSame(204, $this->client->getResponse()->getStatusCode());

        /** @var User $user */
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['email' => 'foobar@example.com']);
        static::assertNotNull($user, 'The user foobar@example.com must be persisted.');
        static::assertNotNull($user->getPassword(), 'The password of user foobar@example.com must not be null.');

        /** @var TransportInterface $transport */
        $transport = static::$container->get('messenger.transport.sync');
        $envelopes = $transport->get();
        static::assertCount(1, $envelopes);
        static::assertInstanceOf(SendRegistrationConfirmationMail::class, $envelopes[0]->getMessage());
        static::assertSame($user->getId()->toString(), $envelopes[0]->getMessage()->getUserId()->toString());
    }

    /**
     * @group functional
     * @group security
     */
    public function testRegistrationLowPassword(): void
    {
        $this->client->xmlHttpRequest('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'foobar@example.com',
            'password' => '123', // low password
        ]));

        static::assertSame(400, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        static::assertSame('invalid_form', $json['error']);
        static::assertSame("Some extra characters and you'll have the 6 required. ;-)", $json['formErrors']['violations'][0]['title']);
    }

    /**
     * @group functional
     * @group security
     */
    public function testRegistrationNotEmail(): void
    {
        $this->client->xmlHttpRequest('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'foobar', // not email
            'password' => '123456',
        ]));

        static::assertSame(400, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        static::assertSame('invalid_form', $json['error']);
        static::assertSame('This value is not a valid email address.', $json['formErrors']['violations'][0]['title']);
    }

    /**
     * @group functional
     * @group security
     */
    public function testRegistrationEmailAlreadyTaken(): void
    {
        $this->client->xmlHttpRequest('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'foo@example.com', // already taken
            'password' => '123456',
        ]));

        static::assertSame(400, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        static::assertSame('invalid_form', $json['error']);
        static::assertSame('This email is already taken. Please choose another.', $json['formErrors']['violations'][0]['title']);
    }
}
