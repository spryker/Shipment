<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Persistence\Propel\Mapper;

use ArrayObject;
use Generated\Shared\Transfer\CurrencyTransfer;
use Generated\Shared\Transfer\MoneyValueTransfer;
use Generated\Shared\Transfer\ShipmentMethodTransfer;
use Generated\Shared\Transfer\ShipmentPriceTransfer;
use Orm\Zed\Currency\Persistence\SpyCurrency;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethod;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethodPrice;

class ShipmentMethodMapper implements ShipmentMethodMapperInterface
{
    /**
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $shipmentMethodTransfer
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $salesShipmentMethodEntity
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethod
     */
    public function mapShipmentMethodTransferToShipmentMethodEntity(
        ShipmentMethodTransfer $shipmentMethodTransfer,
        SpyShipmentMethod $salesShipmentMethodEntity
    ): SpyShipmentMethod {
        $salesShipmentMethodEntity->fromArray($shipmentMethodTransfer->toArray());

        return $salesShipmentMethodEntity;
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $salesShipmentMethodEntity
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $shipmentMethodTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer
     */
    public function mapShipmentMethodEntityToShipmentMethodTransfer(
        SpyShipmentMethod $salesShipmentMethodEntity,
        ShipmentMethodTransfer $shipmentMethodTransfer
    ): ShipmentMethodTransfer {
        $shipmentMethodTransfer = $shipmentMethodTransfer->fromArray($salesShipmentMethodEntity->toArray(), true);
        $shipmentMethodTransfer->setCarrierName($salesShipmentMethodEntity->getShipmentCarrier()->getName());
        $shipmentMethodTransfer->setPrices($this->getPriceCollection($salesShipmentMethodEntity));

        return $shipmentMethodTransfer;
    }

    /**
     * @param \Orm\Zed\Currency\Persistence\SpyCurrency $currencyEntity
     * @param \Generated\Shared\Transfer\CurrencyTransfer $currencyTransfer
     *
     * @return \Generated\Shared\Transfer\CurrencyTransfer
     */
    public function mapCurrencyEntityToCurrencyTransfer(
        SpyCurrency $currencyEntity,
        CurrencyTransfer $currencyTransfer
    ): CurrencyTransfer {
        return $currencyTransfer->fromArray($currencyEntity->toArray(), true);
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPrice $shipmentMethodPrice
     * @param \Generated\Shared\Transfer\ShipmentPriceTransfer $shipmentPriceTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentPriceTransfer
     */
    public function mapShipmentMethodPriceEntityToShipmentPriceTransfer(
        SpyShipmentMethodPrice $shipmentMethodPrice,
        ShipmentPriceTransfer $shipmentPriceTransfer
    ): ShipmentPriceTransfer {
        return $shipmentPriceTransfer->fromArray($shipmentMethodPrice->toArray(), true);
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPrice $shipmentMethodPriceEntity
     * @param \Generated\Shared\Transfer\MoneyValueTransfer $moneyValueTransfer
     *
     * @return \Generated\Shared\Transfer\MoneyValueTransfer
     */
    public function mapShipmentMethodPriceEntityToMoneyValueTransfer(
        SpyShipmentMethodPrice $shipmentMethodPriceEntity,
        MoneyValueTransfer $moneyValueTransfer
    ): MoneyValueTransfer {
        $moneyValueTransfer = $moneyValueTransfer->fromArray($shipmentMethodPriceEntity->toArray(), true);
        $moneyValueTransfer
            ->setIdEntity($shipmentMethodPriceEntity->getIdShipmentMethodPrice())
            ->setNetAmount($shipmentMethodPriceEntity->getDefaultNetPrice())
            ->setGrossAmount($shipmentMethodPriceEntity->getDefaultGrossPrice());

        $currencyTransfer = $this->mapCurrencyEntityToCurrencyTransfer(
            $shipmentMethodPriceEntity->getCurrency(),
            new CurrencyTransfer()
        );
        $moneyValueTransfer->setCurrency($currencyTransfer);

        return $moneyValueTransfer;
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $salesShipmentMethodEntity
     *
     * @return \ArrayObject|\Generated\Shared\Transfer\MoneyValueTransfer[]
     */
    protected function getPriceCollection(SpyShipmentMethod $salesShipmentMethodEntity): ArrayObject
    {
        $moneyValueCollection = new ArrayObject();
        foreach ($salesShipmentMethodEntity->getShipmentMethodPrices() as $shipmentMethodPriceEntity) {
            $moneyValueTransfer = $this->mapShipmentMethodPriceEntityToMoneyValueTransfer(
                $shipmentMethodPriceEntity,
                new MoneyValueTransfer()
            );

            $moneyValueCollection->append($moneyValueTransfer);
        }

        return $moneyValueCollection;
    }
}
