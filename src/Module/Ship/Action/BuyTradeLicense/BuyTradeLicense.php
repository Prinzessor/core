<?php

declare(strict_types=1);

namespace Stu\Module\Ship\Action\BuyTradeLicense;

use request;
use Ship;
use Stu\Module\Control\ActionControllerInterface;
use Stu\Module\Control\GameControllerInterface;
use Stu\Module\Ship\Lib\ShipLoaderInterface;
use Stu\Module\Ship\View\ShowTradeMenu\ShowTradeMenu;
use Stu\Module\Trade\Lib\TradeLibFactoryInterface;
use Stu\Orm\Repository\TradeLicenseRepositoryInterface;
use TradePost;
use TradeStorage;

final class BuyTradeLicense implements ActionControllerInterface
{
    public const ACTION_IDENTIFIER = 'B_PAY_TRADELICENCE';

    private $shipLoader;

    private $tradeLicenseRepository;

    private $tradeLibFactory;

    public function __construct(
        ShipLoaderInterface $shipLoader,
        TradeLicenseRepositoryInterface $tradeLicenseRepository,
        TradeLibFactoryInterface $tradeLibFactory
    ) {
        $this->shipLoader = $shipLoader;
        $this->tradeLicenseRepository = $tradeLicenseRepository;
        $this->tradeLibFactory = $tradeLibFactory;
    }

    public function handle(GameControllerInterface $game): void
    {
        $game->setView(ShowTradeMenu::VIEW_IDENTIFIER);

        $userId = $game->getUser()->getId();

        $ship = $this->shipLoader->getByIdAndUser(
            request::indInt('id'),
            $userId
        );

        /**
         * @var TradePost $tradepost
         */
        $tradepost = ResourceCache()->getObject('tradepost', request::getIntFatal('postid'));

        if (!checkPosition($ship, $tradepost->getShip())) {
            return;
        }
        $targetId = request::getIntFatal('target');
        $mode = request::getStringFatal('method');

        if ($this->tradeLicenseRepository->getAmountByUser($userId) >= MAX_TRADELICENCE_COUNT) {
            return;
        }

        if ($this->tradeLicenseRepository->hasLicenseByUserAndTradePost($userId, (int) $tradepost->getId())) {
            return;
        }
        switch ($mode) {
            case 'ship':
                /** @var Ship $obj */
                $obj = ResourceCache()->getObject('ship', $targetId);
                if (!$obj->ownedByCurrentUser()) {
                    return;
                }
                if (!checkPosition($tradepost->getShip(), $obj)) {
                    return;
                }

                $commodityId = (int) $tradepost->getLicenceCostGood()->getId();

                $storage = $obj->getStorage()[$commodityId] ?? null;
                if ($storage === null || $storage->getAmount() < $tradepost->calculateLicenceCost()) {
                    return;
                }
                $obj->lowerStorage($commodityId, $tradepost->calculateLicenceCost());
                break;
            case 'account':
                $targetTradepost = ResourceCache()->getObject('tradepost', $targetId);
                $storageManager = $this->tradeLibFactory->createTradePostStorageManager($targetTradepost, $userId);
                $commodityId = (int) $tradepost->getLicenceCostGood()->getId();
                $costs = (int) $tradepost->calculateLicenceCost();

                $stor = $storageManager->getStorage()[$commodityId] ?? null;
                if ($stor === null) {
                    return;
                }
                if ($stor->getAmount() < $costs) {
                    return;
                }
                if ($targetTradepost->getTradeNetwork() != $tradepost->getTradeNetwork()) {
                    return;
                }

                $storageManager->lowerStorage($commodityId, $costs);
                break;
            default:
                return;
        }
        $licence = $this->tradeLicenseRepository->prototype();
        $licence->setTradePostId((int) $tradepost->getId());
        $licence->setUserId($userId);
        $licence->setDate(time());

        $this->tradeLicenseRepository->save($licence);
    }

    public function performSessionCheck(): bool
    {
        return true;
    }
}
