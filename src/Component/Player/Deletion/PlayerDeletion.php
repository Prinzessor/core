<?php

declare(strict_types=1);

namespace Stu\Component\Player\Deletion;

use Stu\Component\Player\Deletion\Handler\PlayerDeletionHandlerInteface;
use Stu\Orm\Entity\UserInterface;
use Stu\Orm\Repository\UserRepositoryInterface;

final class PlayerDeletion implements PlayerDeletionInterface
{

    public const USER_IDLE_TIME = 120960000;

    private $userRepository;

    private $deletionHandler;

    /**
     * @param UserRepositoryInterface $userRepository
     * @param PlayerDeletionHandlerInteface[] $deletionHandler
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        array $deletionHandler
    ) {
        $this->userRepository = $userRepository;
        $this->deletionHandler = $deletionHandler;
    }

    public function handleIdleUsers(): void
    {
        array_walk(
            $this->userRepository->getIdlePlayer(
                time() - PlayerDeletion::USER_IDLE_TIME,
                [101]
            ),
            function (UserInterface $user): void {
                $this->delete($user);
            }
        );
    }

    public function handleReset(): void
    {
        array_walk(
            $this->userRepository->getActualPlayer(),
            function (UserInterface $user): void {
                $this->delete($user);
            }
        );
    }

    private function delete(UserInterface $user): void
    {
        array_walk(
            $this->deletionHandler,
            function (PlayerDeletionHandlerInteface $handler) use ($user): void {
                $handler->delete($user);
            }
        );
    }
}
