<?php

declare(strict_types=1);

namespace Stu\Component\Player\Deletion\Handler;

use Stu\Orm\Entity\UserInterface;
use Stu\Orm\Repository\SessionStringRepositoryInterface;
use Stu\Orm\Repository\UserProfileVisitorRepositoryInterface;
use Stu\Orm\Repository\UserRepositoryInterface;

final class UserDeletionHandler implements PlayerDeletionHandlerInteface
{

    private $sessionStringRepository;

    private $userProfileVisitorRepository;

    private $userRepository;

    public function __construct(
        SessionStringRepositoryInterface $sessionStringRepository,
        UserProfileVisitorRepositoryInterface $userProfileVisitorRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->sessionStringRepository = $sessionStringRepository;
        $this->userProfileVisitorRepository = $userProfileVisitorRepository;
        $this->userRepository = $userRepository;
    }

    public function delete(UserInterface $user): void
    {
        $this->sessionStringRepository->truncate($user->getId());
        $this->userProfileVisitorRepository->truncateByUser($user->getId());
        $this->userRepository->delete($user);
    }
}
