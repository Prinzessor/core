<?php

declare(strict_types=1);

namespace Stu\Module\Colony\Action\DeactivateBuildings;

use Colfields;
use Good;
use request;
use Stu\Control\ActionControllerInterface;
use Stu\Control\GameControllerInterface;
use Stu\Module\Colony\Lib\BuildingActionInterface;
use Stu\Module\Colony\Lib\ColonyLoaderInterface;
use Stu\Module\Colony\View\ShowColony\ShowColony;

final class DeactivateBuildings implements ActionControllerInterface
{

    public const ACTION_IDENTIFIER = 'B_MULTIPLE_DEACTIVATION';

    private $colonyLoader;

    private $buildingAction;

    public function __construct(
        ColonyLoaderInterface $colonyLoader,
        BuildingActionInterface $buildingAction
    ) {
        $this->colonyLoader = $colonyLoader;
        $this->buildingAction = $buildingAction;
    }

    public function handle(GameControllerInterface $game): void
    {
        $colony = $this->colonyLoader->byIdAndUser(
            request::indInt('id'),
            $game->getUser()->getId()
        );

        $colonyId = $colony->getId();

        foreach (request::postArrayFatal('selfields') as $key) {
            $field = Colfields::getByColonyField($key, $colonyId);
            $this->buildingAction->deactivate($colony, $field, $game);
        }

        $list = Colfields::getListBy('colonies_id=' . $colony->getId() . ' AND buildings_id>0');
        usort($list, 'compareBuildings');

        $game->setTemplateVar('BUILDING_LIST', $list);
        $game->setTemplateVar('USEABLE_GOOD_LIST', Good::getListByActiveBuildings($colony->getId()));

        $game->setView(ShowColony::VIEW_IDENTIFIER, ['COLONY_MENU' => MENU_BUILDINGS]);
    }

    public function performSessionCheck(): bool
    {
        return true;
    }
}