<?php

namespace Stu\Lib;

use Stu\Module\Alliance\Lib\AllianceActionManagerInterface;
use Stu\Module\Ship\Lib\ShipRemoverInterface;
use Stu\Orm\Entity\UserInterface;
use Stu\Orm\Repository\AllianceJobRepositoryInterface;
use Stu\Orm\Repository\ContactRepositoryInterface;
use Stu\Orm\Repository\CrewRepositoryInterface;
use Stu\Orm\Repository\DatabaseUserRepositoryInterface;
use Stu\Orm\Repository\FleetRepositoryInterface;
use Stu\Orm\Repository\KnCommentRepositoryInterface;
use Stu\Orm\Repository\KnPostRepositoryInterface;
use Stu\Orm\Repository\NoteRepositoryInterface;
use Stu\Orm\Repository\PrivateMessageFolderRepositoryInterface;
use Stu\Orm\Repository\PrivateMessageRepositoryInterface;
use Stu\Orm\Repository\ResearchedRepositoryInterface;
use Stu\Orm\Repository\RpgPlotMemberRepositoryInterface;
use Stu\Orm\Repository\RpgPlotRepositoryInterface;
use Stu\Orm\Repository\SessionStringRepositoryInterface;
use Stu\Orm\Repository\ShipBuildplanRepositoryInterface;
use Stu\Orm\Repository\ShipRepositoryInterface;
use Stu\Orm\Repository\TradeLicenseRepositoryInterface;
use Stu\Orm\Repository\TradeOfferRepositoryInterface;
use Stu\Orm\Repository\TradeShoutboxRepositoryInterface;
use Stu\Orm\Repository\TradeStorageRepositoryInterface;
use Stu\Orm\Repository\UserProfileVisitorRepositoryInterface;
use Stu\Orm\Repository\UserRepositoryInterface;

class UserDeletion
{

    public const USER_IDLE_TIME = 120960000;
    private $user;

    public function __construct(UserInterface $user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function handleAlliance()
    {
        // @todo refactor
        global $container;

        $allianceJobRepo = $container->get(AllianceJobRepositoryInterface::class);
        $allianceActionManager = $container->get(AllianceActionManagerInterface::class);

        foreach ($allianceJobRepo->getByUser((int) $this->getUser()->getId()) as $job) {
            if ($job->getType() === ALLIANCE_JOBS_FOUNDER) {
                $alliance = $job->getAlliance();

                if ($alliance->getSuccessor() === null) {
                    $allianceActionManager->delete((int) $alliance->getId());
                } else {
                    $successorUserId = $alliance->getSuccessor()->getUserId();

                    $allianceJobRepo->truncateByUser($successorUserId);

                    $allianceActionManager->setJobForUser(
                        (int) $alliance->getId(),
                        $successorUserId,
                        ALLIANCE_JOBS_FOUNDER
                    );
                }
            }

            $allianceJobRepo->delete($job);
        }
    }

    public function handleBuildplans()
    {
        // @todo refactor
        global $container;

        $shipBuildplanRepo = $container->get(ShipBuildplanRepositoryInterface::class);

        $result = $shipBuildplanRepo->getByUser((int) $this->getUser()->getId());
        foreach ($result as $obj) {
            $shipBuildplanRepo->delete($obj);
        }
    }

    public function handleColonies()
    {
        /**
         * @todo Re-implement colony abandoning
         */
//        $result = Colony::getListBy('user_id=' . $this->getUser()->getId());
//        foreach ($result as $key => $obj) {
//            $obj->deepDelete();
//        }
    }

    public function handleContactlist()
    {
        // @todo refactor
        global $container;

        $userId = $this->getUser()->getId();

        $container->get(ContactRepositoryInterface::class)->truncateByUserAndOpponent($userId, $userId);
    }

    public function handleCrew()
    {
        // @todo refactor
        global $container;

        $container->get(CrewRepositoryInterface::class)->truncateByUser(
            (int) $this->getUser()->getId()
        );
    }

    public function handleDatabaseEntries()
    {
        // @todo refactor
        global $container;

        $container->get(DatabaseUserRepositoryInterface::class)->truncateByUserId(
            (int) $this->getUser()->getId()
        );
    }

    public function handleFleets()
    {
        // @todo refactor
        global $container;

        $container->get(FleetRepositoryInterface::class)->truncateByUser((int) $this->getUser()->getId());
    }

    public function handleIgnoreList()
    {
        // @todo delete ignorelist
        //Contactlist::truncate('WHERE user_id=' . $this->getUser()->getId());
    }

    public function handleKnPostings()
    {
        // @todo refactor
        global $container;

        $knPostRepo = $container->get(KnPostRepositoryInterface::class);
        $userRepo = $container->get(UserRepositoryInterface::class);

        foreach ($knPostRepo->getByUser((int) $this->getUser()->getId()) as $key => $obj) {
            $obj->setUserName($this->getUser()->getUser());
            $obj->setUser($userRepo->find(USER_NOONE));

            $knPostRepo->save($obj);
        }
    }

    public function handleKnComments()
    {
        // @todo refactor
        global $container;

        $container->get(KnCommentRepositoryInterface::class)->truncateByUser((int) $this->getUser()->getId());
    }

    public function handleNotes()
    {
        // @todo refactor
        global $container;

        $container->get(NoteRepositoryInterface::class)->truncateByUserId((int) $this->getUser()->getId());
    }

    public function handleRPGPlots()
    {
        // @todo refactor
        global $container;

        $rpgPlotMemberRepo = $container->get(RpgPlotMemberRepositoryInterface::class);
        $rpgPlotRepository = $container->get(RpgPlotRepositoryInterface::class);
        $userRepository = $container->get(UserRepositoryInterface::class);

        foreach ($rpgPlotRepository->getByFoundingUser((int) $this->getUser()->getId()) as $obj) {

            $item = $rpgPlotMemberRepo->getByPlotAndUser((int) $obj->getId(), (int) $this->getUser()->getId());
            if ($item !== null) {
                $rpgPlotMemberRepo->delete($item);
            }
            if ($obj->getMembers()) {
                $member = current($obj->getMembers());
                $obj->setUser($member->getUser());

                $rpgPlotRepository->save($obj);
                return;
            }
            $obj->setUser($userRepository->find(USER_NOONE));

            $rpgPlotRepository->save($obj);
        }
    }

    public function handlePMCategories()
    {
        // @todo refactor
        global $container;

        $privateMessageFolderRepo = $container->get(PrivateMessageFolderRepositoryInterface::class);
        $privateMessageRepo = $container->get(PrivateMessageRepositoryInterface::class);

        $result = $privateMessageFolderRepo->getOrderedByUser((int) $this->getUser()->getId());
        foreach ($result as $folder) {
            $privateMessageRepo->truncateByFolder($folder->getId());

            $privateMessageFolderRepo->delete($folder);
        }
    }

    public function handleResearch()
    {
        // @todo refactor
        global $container;

        $container->get(ResearchedRepositoryInterface::class)->truncateForUser((int) $this->getUser()->getId());
    }

    public function handleShips()
    {
        // @todo refactor
        global $container;
        $shipRemover = $container->get(ShipRemoverInterface::class);
        $shipRepo = $container->get(ShipRepositoryInterface::class);

        foreach ($shipRepo->getByUser($this->getUser()->getId()) as $obj) {
            $shipRemover->remove($obj);
        }
    }

    public function handleTrade()
    {
        $userId = (int) $this->getUser()->getId();

        // @todo refactor
        global $container;

        $container->get(TradeLicenseRepositoryInterface::class)->truncateByUser($userId);
        $container->get(TradeOfferRepositoryInterface::class)->truncateByUser($userId);
        $container->get(TradeStorageRepositoryInterface::class)->truncateByUser($userId);
        $container->get(TradeShoutboxRepositoryInterface::class)->truncateByUser($userId);
    }

    static function handle($userlist)
    {
        foreach ($userlist as $key => $user) {
            $handler = new UserDeletion($user);
            $handler->handleAlliance();
            $handler->handleBuildplans();
            $handler->handleColonies();
            $handler->handleContactlist();
            $handler->handleCrew();
            $handler->handleDatabaseEntries();
            $handler->handleFleets();
            $handler->handleIgnoreList();
            $handler->handleKnPostings();
            $handler->handleKnComments();
            $handler->handleNotes();
            $handler->handleRPGPlots();
            $handler->handlePMCategories();
            $handler->handleResearch();
            $handler->handleShips();
            $handler->handleTrade();

            DB()->query('DELETE FROM stu_user_map WHERE user_id='.$user->getId());
            DB()->query('DELETE FROM stu_user_iptable WHERE user_id='.$user->getId());

            // @todo refactor
            global $container;

            $container->get(SessionStringRepositoryInterface::class)->truncate((int) $user->getId());
            $container->get(UserProfileVisitorRepositoryInterface::class)->truncateByUser((int) $user->getId());

            $user->deleteFromDatabase();
        }
    }

    public static function handleIdleUsers()
    {
        // @todo refactor
        global $container;

        self::handle(
            $container->get(UserRepositoryInterface::class)->getIdlePlayer(
                time() - UserDeletion::USER_IDLE_TIME,
                getAdminUserIds()
            )
        );
    }

    public static function handleReset()
    {
        // @todo refactor
        global $container;

        self::handle($container->get(UserRepositoryInterface::class)->getActualPlayer());
    }

}
