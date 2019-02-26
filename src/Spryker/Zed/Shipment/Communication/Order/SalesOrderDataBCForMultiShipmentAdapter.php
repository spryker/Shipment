<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Communication\Order;

use Generated\Shared\Transfer\AddressTransfer;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\ShipmentTransfer;

/**
 * @deprecated Exists for Backward Compatibility reasons only.
 */
class SalesOrderDataBCForMultiShipmentAdapter implements SalesOrderDataBCForMultiShipmentAdapterInterface
{
    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    public function adapt(OrderTransfer $orderTransfer): OrderTransfer
    {
        if ($this->assertThatItemTransfersHaveShipmentAndAddress($orderTransfer)) {
            return $orderTransfer;
        }

        if ($this->assertThatOrderHasNoAddressTransfer($orderTransfer)) {
            return $orderTransfer;
        }

        if ($this->assertThatOrderHasNoShipment($orderTransfer)) {
            return $orderTransfer;
        }

        foreach ($orderTransfer->getItems() as $itemTransfer) {
            if ($this->assertThatItemTransferHasShipment($itemTransfer)) {
                continue;
            }

            $this->setItemTransferShipmentAndShippingAddressForBC($itemTransfer, $orderTransfer);
        }

        return $orderTransfer;
    }

    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool
     */
    protected function assertThatItemTransfersHaveShipmentAndAddress(OrderTransfer $orderTransfer): bool
    {
        foreach ($orderTransfer->getItems() as $itemTransfer) {
            if ($itemTransfer->getShipment() === null
                || $itemTransfer->getShipment()->getShippingAddress() === null
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool
     */
    protected function assertThatOrderHasNoAddressTransfer(OrderTransfer $orderTransfer): bool
    {
        return $orderTransfer->getShippingAddress() === null;
    }

    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool
     */
    protected function assertThatOrderHasNoShipment(OrderTransfer $orderTransfer): bool
    {
        return $orderTransfer->getShipment() === null;
    }

    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     *
     * @return bool
     */
    protected function assertThatItemTransferHasShipment(ItemTransfer $itemTransfer): bool
    {
        return ($itemTransfer->getShipment() !== null);
    }

    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentTransfer
     */
    protected function getShipmentTransferForBC(ItemTransfer $itemTransfer, OrderTransfer $orderTransfer): ShipmentTransfer
    {
        if ($itemTransfer->getShipment() !== null) {
            return $itemTransfer->getShipment();
        }

        return $orderTransfer->getShipment();
    }

    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\AddressTransfer
     */
    protected function getShippingAddressTransferForBC(ItemTransfer $itemTransfer, OrderTransfer $orderTransfer): AddressTransfer
    {
        if ($itemTransfer->getShipment()->getShippingAddress() !== null) {
            return $itemTransfer->getShipment()->getShippingAddress();
        }

        return $orderTransfer->getShippingAddress();
    }

    /**
     * @deprecated Exists for Backward Compatibility reasons only.
     *
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return void
     */
    protected function setItemTransferShipmentAndShippingAddressForBC(ItemTransfer $itemTransfer, OrderTransfer $orderTransfer): void
    {
        $shipmentTransfer = $this->getShipmentTransferForBC($itemTransfer, $orderTransfer);
        $itemTransfer->setShipment($shipmentTransfer);

        $shippingAddressTransfer = $this->getShippingAddressTransferForBC($itemTransfer, $orderTransfer);
        $shipmentTransfer->setShippingAddress($shippingAddressTransfer);
    }
}
