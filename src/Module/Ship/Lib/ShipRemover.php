<?php

declare(strict_types=1);

namespace Stu\Module\Ship\Lib;

use Stu\Component\Game\GameEnum;
use Stu\Component\Ship\ShipEnum;
use Stu\Component\Ship\Storage\ShipStorageManagerInterface;
use Stu\Component\Ship\System\ShipSystemManagerInterface;
use Stu\Module\Ship\Lib\ShipLeaverInterface;
use Stu\Orm\Entity\ShipInterface;
use Stu\Orm\Repository\FleetRepositoryInterface;
use Stu\Orm\Repository\ShipCrewRepositoryInterface;
use Stu\Orm\Repository\ShipRepositoryInterface;
use Stu\Orm\Repository\ShipRumpRepositoryInterface;
use Stu\Orm\Repository\ShipStorageRepositoryInterface;
use Stu\Orm\Repository\ShipSystemRepositoryInterface;
use Stu\Orm\Repository\UserRepositoryInterface;

final class ShipRemover implements ShipRemoverInterface
{
    private ShipSystemRepositoryInterface $shipSystemRepository;

    private ShipStorageRepositoryInterface $shipStorageRepository;
    
    private ShipStorageManagerInterface $shipStorageManager;

    private ShipCrewRepositoryInterface $shipCrewRepository;

    private FleetRepositoryInterface $fleetRepository;

    private ShipRepositoryInterface $shipRepository;

    private UserRepositoryInterface $userRepository;

    private ShipRumpRepositoryInterface $shipRumpRepository;

    private ShipSystemManagerInterface $shipSystemManager;

    private ShipLeaverInterface $shipLeaver;

    public function __construct(
        ShipSystemRepositoryInterface $shipSystemRepository,
        ShipStorageRepositoryInterface $shipStorageRepository,
        ShipStorageManagerInterface $shipStorageManager,
        ShipCrewRepositoryInterface $shipCrewRepository,
        FleetRepositoryInterface $fleetRepository,
        ShipRepositoryInterface $shipRepository,
        UserRepositoryInterface $userRepository,
        ShipRumpRepositoryInterface $shipRumpRepository,
        ShipSystemManagerInterface $shipSystemManager,
        ShipLeaverInterface $shipLeaver
    ) {
        $this->shipSystemRepository = $shipSystemRepository;
        $this->shipStorageRepository = $shipStorageRepository;
        $this->shipStorageManager = $shipStorageManager;
        $this->shipCrewRepository = $shipCrewRepository;
        $this->fleetRepository = $fleetRepository;
        $this->shipRepository = $shipRepository;
        $this->userRepository = $userRepository;
        $this->shipRumpRepository = $shipRumpRepository;
        $this->shipSystemManager = $shipSystemManager;
        $this->shipLeaver = $shipLeaver;
    }

    public function destroy(ShipInterface $ship): ?string
    {
        $msg = null;

        $this->shipSystemManager->deactivateAll($ship);

        if ($ship->isFleetLeader()) {
            $this->changeFleetLeader($ship);
        }

        //leave ship if there is crew
        if ($ship->getCrewCount() > 0)
        {
            $msg = $this->shipLeaver->leave($ship);
        }

        /**
         * this is buggy :(
         * throws ORMInvalidArgumentException
         * 
         if ($ship->getRump()->isEscapePods())
         {
             $this->remove($ship);
             return $msg;
            }
        */

        $ship->setFormerRumpId($ship->getRump()->getId());
        $ship->setRump($this->shipRumpRepository->find(ShipEnum::TRUMFIELD_CLASS));
        $ship->setHuell((int) round($ship->getMaxHuell()/20));
        $ship->setUser($this->userRepository->find(GameEnum::USER_NOONE));
        $ship->setBuildplan(null);
        $ship->setShield(0);
        $ship->setEps(0);
        $ship->setAlertState(1);
        $ship->setDockedTo(null);
        $ship->setName(_('Trümmer'));
        $ship->setIsDestroyed(true);
        $ship->setFleet(null);
        $ship->cancelRepair();

        $this->leaveSomeIntactModules($ship);

        $this->shipSystemRepository->truncateByShip((int) $ship->getId());
        // @todo Torpedos löschen

        $this->shipRepository->save($ship);

        return $msg;
    }

    private function leaveSomeIntactModules($ship): void
    {
        $intactModules = [];

        foreach($ship->getSystems() as $system)
        {
            if ($system->getModule() !== null
                && $system->getStatus() == 100)
            {
                $module = $system->getModule();

                if (!array_key_exists($module->getId(), $intactModules))
                {
                    $intactModules[$module->getId()] = $module;
                }
            }
        }

        //leave 50% of all intact modules
        $leaveCount = (int) ceil(count($intactModules) / 2);
        for ($i = 1; $i <= $leaveCount; $i++)
        {
            $module = $intactModules[array_rand($intactModules)];
            unset($intactModules[$module->getId()]);

            $this->shipStorageManager->upperStorage(
                $ship,
                $module->getCommodity(),
                1
            );
        }
    }

    public function remove(ShipInterface $ship): void
    {
        if ($ship->isFleetLeader()) {
            $this->changeFleetLeader($ship);
        }
        $ship->deactivateTraktorBeam();

        foreach ($ship->getStorage() as $item) {
            $this->shipStorageRepository->delete($item);
        }
        $this->shipCrewRepository->truncateByShip((int) $ship->getId());

        $this->shipRepository->delete($ship);
    }

    private function changeFleetLeader(ShipInterface $obj): void
    {
        $ship = current(
            array_filter(
                $obj->getFleet()->getShips()->toArray(),
                function (ShipInterface $ship) use ($obj): bool {
                    return $ship !== $obj;
                }
            )
        );

        $fleet = $obj->getFleet();

        $obj->setFleet(null);
        $fleet->getShips()->removeElement($obj);

        $this->shipRepository->save($obj);

        if (!$ship) {
            $this->fleetRepository->delete($fleet);

            return;
        }
        $fleet->setLeadShip($ship);

        $this->fleetRepository->save($fleet);
    }
}
