<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Shipment\Items;

use ArrayObject;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\ShipmentGroupTransfer;
use Generated\Shared\Transfer\ShipmentTransfer;

class ItemsGrouper implements ItemsGrouperInterface
{
    protected const SHIPMENT_TRANSFER_KEY_PATTERN = '%s-%s-%s';

    /**
     * @param iterable|\Generated\Shared\Transfer\ItemTransfer[] $itemTransfers
     *
     * @return \ArrayObject|\Generated\Shared\Transfer\ShipmentGroupTransfer[]
     */
    public function groupByShipment(iterable $itemTransfers): ArrayObject
    {
        $shipmentGroupTransfers = new ArrayObject();

        foreach ($itemTransfers as $itemTransfer) {
            $this->assertRequiredShipment($itemTransfer);

            $key = $this->getShipmentHashKey($itemTransfer->getShipment());
            if (!isset($shipmentGroupTransfers[$key])) {
                $shipmentGroupTransfers[$key] = (new ShipmentGroupTransfer())
                    ->setShipment($itemTransfer->getShipment())
                    ->setHash($key);
            }

            $shipmentGroupTransfers[$key]->addItem($itemTransfer);
        }

        return $shipmentGroupTransfers;
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     *
     * @return string
     */
    public function getShipmentHashKey(ShipmentTransfer $shipmentTransfer): string
    {
        return md5(sprintf(
            static::SHIPMENT_TRANSFER_KEY_PATTERN,
            $shipmentTransfer->getMethod() ? $shipmentTransfer->getMethod()->getIdShipmentMethod() : '',
            $shipmentTransfer->getShippingAddress() ? $shipmentTransfer->getShippingAddress()->serialize() : '',
            $shipmentTransfer->getRequestedDeliveryDate()
        ));
    }

    /**
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     *
     * @return void
     */
    protected function assertRequiredShipment(ItemTransfer $itemTransfer): void
    {
        $itemTransfer->requireShipment();
        /**
         * @todo Remove this two checks.
         */
        $itemTransfer->getShipment()->requireMethod();
        $itemTransfer->getShipment()->requireShippingAddress();
    }
}
