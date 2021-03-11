<?php

namespace App\Tests\Controller\Security;

use App\Tests\WebTestCase;

class RegistrationConfirmationControllerTest extends WebTestCase
{
    /**
     * @group functional
     * @group security
     */
    public function testRegistrationConfirmation(): void
    {
        $crawler = $this->client->request('GET', '/register-confirmation', [
            'token' => 'ltkE9lPcaZzPKKh0kCtN9Mywa7ugqrfoi1L3MUjn67jbAusfQW576s4ozTHNjMHN',
            'id' => '65ca8ef6-bcf6-46ff-b9ae-1ade7a51ec26', // user_foo@example.com
        ]);

        static::assertSame(200, $this->client->getResponse()->getStatusCode());
        static::assertStringContainsString('Congrats! You are now confirmed on Fleet Manager. You will be redirected to the homepage in 5 seconds.', $crawler->filter('.alert-success')->text(null, false));
    }

    /**
     * @group functional
     * @group security
     */
    public function testRegistrationConfirmationAlreadyConfirmed(): void
    {
        $crawler = $this->client->request('GET', '/register-confirmation', [
            'token' => 'ImDM9wv50tfoM9hfJAick4MmAvXpPaGadyqWC5nDUx2UVgGIq8vkNnu36HeoSm39',
            'id' => 'c869de61-1a88-4aaa-a2f9-9b4b116afe85', // user_alreadyconfirmed@example.com
        ]);

        static::assertSame(200, $this->client->getResponse()->getStatusCode());
        static::assertStringContainsString('Sorry, you have already confirmed your registration. Feel free to login to the Fleet Manager.', $crawler->filter('.alert-danger')->text(null, false));
    }

    /**
     * @group functional
     * @group security
     */
    public function testRegistrationConfirmationBadToken(): void
    {
        $crawler = $this->client->request('GET', '/register-confirmation', [
            'token' => 'bad_token',
            'id' => '65ca8ef6-bcf6-46ff-b9ae-1ade7a51ec26', // user_foo@example.com
        ]);

        static::assertSame(200, $this->client->getResponse()->getStatusCode());
        static::assertStringContainsString('Sorry, this confirmation does not exist. Please check the web address in your email message.', $crawler->filter('.alert-danger')->text(null, false));
    }

    /**
     * @group functional
     * @group security
     */
    public function testRegistrationConfirmationBadUser(): void
    {
        $crawler = $this->client->request('GET', '/register-confirmation', [
            'token' => 'random_token',
            'id' => '4e47d4f1-787c-447a-9e77-2a09fe41cc04', // not exist
        ]);

        static::assertSame(200, $this->client->getResponse()->getStatusCode());
        static::assertStringContainsString('Sorry, this confirmation does not exist. Please check the web address in your email message.', $crawler->filter('.alert-danger')->text(null, false));
    }
}
