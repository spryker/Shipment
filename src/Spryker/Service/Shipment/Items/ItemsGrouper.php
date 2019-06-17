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
use Spryker\Service\Shipment\Dependency\Service\ShipmentToCustomerServiceInterface;

class ItemsGrouper implements ItemsGrouperInterface
{
    protected const SHIPMENT_TRANSFER_KEY_PATTERN = '%s-%s-%s';

    /**
     * @var \Spryker\Service\Shipment\Dependency\Service\ShipmentToCustomerServiceInterface
     */
    protected $customerService;

    /**
     * @param \Spryker\Service\Shipment\Dependency\Service\ShipmentToCustomerServiceInterface $customerService
     */
    public function __construct(
        ShipmentToCustomerServiceInterface $customerService
    ) {
        $this->customerService = $customerService;
    }

    /**
     * @param iterable|\Generated\Shared\Transfer\ItemTransfer[] $itemTransfers
     *
     * @return \ArrayObject|\Generated\Shared\Transfer\ShipmentGroupTransfer[]
     */
    public function groupByShipment(iterable $itemTransfers): ArrayObject
    {
        $shipmentGroupTransfers = [];

        foreach ($itemTransfers as $itemTransfer) {
            $this->assertRequiredShipment($itemTransfer);

            $key = $this->getShipmentHashKey($itemTransfer->getShipment());
            if (!isset($shipmentGroupTransfers[$key])) {
                $shipmentGroupTransfers[$key] = $this->createShipmentGroupTransfer($itemTransfer, $key);
            }

            $shipmentGroupTransfers[$key]->addItem($itemTransfer);
        }

        return new ArrayObject(array_values($shipmentGroupTransfers));
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
            $shipmentTransfer->getMethod() !== null ? $shipmentTransfer->getMethod()->getIdShipmentMethod() : '',
            $shipmentTransfer->getShippingAddress() !== null ?
                $this->customerService->getUniqueAddressKey($shipmentTransfer->getShippingAddress()) : '',
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
    }

    /**
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     * @param string $key
     *
     * @return \Generated\Shared\Transfer\ShipmentGroupTransfer
     */
    protected function createShipmentGroupTransfer(ItemTransfer $itemTransfer, string $key): ShipmentGroupTransfer
    {
        return (new ShipmentGroupTransfer())
            ->setShipment($itemTransfer->getShipment())
            ->setHash($key);
    }
}