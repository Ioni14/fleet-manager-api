<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\Registration\SendRegistrationConfirmationMail;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Mime\Address;

class SendRegistrationConfirmationMailHandler implements MessageHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private string $registrationFromAddress
    ) {
    }

    public function __invoke(SendRegistrationConfirmationMail $message): void
    {
        /** @var User $user */
        $user = $this->userRepository->find($message->getUserId());
        if ($user === null) {
            throw new \LogicException(sprintf('User %s does not exist.', $message->getUserId()));
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->registrationFromAddress, 'Fleet Manager'))
            ->to($user->getEmail())
            ->subject('Fleet Manager : Registration confirmation')
            ->textTemplate('emails/registration_confirmation.txt.twig')
            ->htmlTemplate('emails/registration_confirmation.html.twig')
            ->context(['user' => $user]);
        $this->mailer->send($email);
    }
}
