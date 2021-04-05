<?php

namespace App\Tests\Acceptance\Home;

use App\Application\Home\NumbersService;
use App\Application\Home\Output\NumbersOutput;
use App\Application\Repository\UserRepositoryInterface;
use App\Domain\UserId;
use App\Entity\User;
use App\Infrastructure\Repository\User\InMemoryUserRepository;
use App\Tests\Acceptance\KernelTestCase;

class NumbersServiceTest extends KernelTestCase
{
    /**
     * @test
     */
    public function it_should_return_right_amount_of_users(): void
    {
        /** @var InMemoryUserRepository $userRepository */
        $userRepository = static::$container->get(UserRepositoryInterface::class);
        $userRepository->setUsers([
            new User(UserId::fromString('00000000-0000-0000-0000-000000000001'), 'Ioni', new \DateTimeImmutable()),
            new User(UserId::fromString('00000000-0000-0000-0000-000000000002'), 'Ashuvidz', new \DateTimeImmutable()),
            new User(UserId::fromString('00000000-0000-0000-0000-000000000003'), 'Lunia', new \DateTimeImmutable()),
        ]);

        /** @var NumbersService $service */
        $service = static::$container->get(NumbersService::class);
        $output = $service->handle();
        static::assertEquals(new NumbersOutput(users: 3), $output);
    }

    /**
     * @test
     */
    public function it_should_return_zero_if_no_users(): void
    {
        /** @var InMemoryUserRepository $userRepository */
        $userRepository = static::$container->get(UserRepositoryInterface::class);
        $userRepository->setUsers([]);

        /** @var NumbersService $service */
        $service = static::$container->get(NumbersService::class);
        $output = $service->handle();
        static::assertEquals(new NumbersOutput(users: 0), $output);
    }
}
