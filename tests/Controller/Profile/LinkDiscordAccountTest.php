<?php

namespace App\Tests\Controller\Profile;

use App\Entity\User;
use App\Tests\WebTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LinkDiscordAccountTest extends WebTestCase
{
    /** @var User */
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->doctrine->getRepository(User::class)->findOneBy(['email' => 'linksocialnetworks-without-citizen@example.com']);
    }

    /**
     * @group functional
     * @group profile
     */
    public function testLinkDiscordNoConflicts(): void
    {
        $this->logIn($this->user);
        $this->client->followRedirects();
        $this->client->request('GET', '/connect/service/discord', [
            'discordId' => '123456',
            'discordTag' => '9876',
            'nickname' => 'foobar',
        ], [], []);

        static::assertSame(200, $this->client->getResponse()->getStatusCode());

        $user = $this->doctrine->getRepository(User::class)->find($this->user->getId());
        static::assertSame('123456', $user->getDiscordId());
        static::assertSame('9876', $user->getDiscordTag());
        static::assertSame('foobar', $user->getNickname());
    }

    /**
     * @group functional
     * @group profile
     */
    public function testLinkDiscordAlreadyTakenWithCitizen(): void
    {
        $this->logIn($this->user);
        $this->client->followRedirects();
        $this->client->request('GET', '/connect/service/discord', [
            'discordId' => '123456789001',
            'discordTag' => '0001',
            'nickname' => 'Ioni',
        ], [], []);

        static::assertSame(200, $this->client->getResponse()->getStatusCode());

        /** @var User $user */
        $user = $this->doctrine->getRepository(User::class)->find($this->user->getId());
        static::assertSame('123456789001', $user->getDiscordId());
        static::assertSame('0001', $user->getDiscordTag());
        static::assertSame('Ioni', $user->getNickname());
        static::assertSame('7275c744-6a69-43c2-9ebf-1491a104d5e7', $user->getCitizen()->getId()->toString());

        static::assertNull($this->doctrine->getRepository(User::class)->find('d92e229e-e743-4583-905a-e02c57eacfe0'), 'The already linked user is not deleted.');
    }

    /**
     * @group functional
     * @group profile
     */
    public function testLinkDiscordAlreadyTakenWithCitizenConflict(): void
    {
        /** @var User $user */
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['email' => 'linksocialnetworks-with-citizen@example.com']);
        $this->logIn($user);
        $this->client->request('GET', '/connect/service/discord', [
            'discordId' => '123456789001',
            'discordTag' => '0001',
            'nickname' => 'Ioni',
        ], [], []);

        static::assertSame(302, $this->client->getResponse()->getStatusCode());

        /** @var RedirectResponse $response */
        $response = $this->client->getResponse();
        static::assertSame('/profile?error=already_linked_discord', $response->getTargetUrl());

        $user = $this->doctrine->getRepository(User::class)->find($user->getId());
        static::assertNull($user->getDiscordId());
        static::assertSame('123456789001', $user->getPendingDiscordId());
    }
}
