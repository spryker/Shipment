<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Business;

use Generated\Shared\Transfer\CheckoutResponseTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Generated\Shared\Transfer\SaveOrderTransfer;
use Generated\Shared\Transfer\ShipmentCarrierTransfer;
use Generated\Shared\Transfer\ShipmentGroupCollectionTransfer;
use Generated\Shared\Transfer\ShipmentGroupResponseTransfer;
use Generated\Shared\Transfer\ShipmentGroupTransfer;
use Generated\Shared\Transfer\ShipmentMethodTransfer;
use Generated\Shared\Transfer\ShipmentTransfer;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethod;
use Spryker\Shared\Shipment\ShipmentConstants;
use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @method \Spryker\Zed\Shipment\Business\ShipmentBusinessFactory getFactory()
 * @method \Spryker\Zed\Shipment\Persistence\ShipmentEntityManagerInterface getEntityManager()
 */
class ShipmentFacade extends AbstractFacade implements ShipmentFacadeInterface
{
    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ShipmentCarrierTransfer $carrierTransfer
     *
     * @return int
     */
    public function createCarrier(ShipmentCarrierTransfer $carrierTransfer)
    {
        $carrierModel = $this->getFactory()->createCarrier();

        return $carrierModel->create($carrierTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\ShipmentCarrierTransfer[]
     */
    public function getCarriers()
    {
        return $this->getFactory()
            ->createShipmentCarrierReader()
            ->getCarriers();
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer[]
     */
    public function getMethods()
    {
        return $this->getFactory()
            ->createMethodReader()
            ->getShipmentMethodTransfers();
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $methodTransfer
     *
     * @return int
     */
    public function createMethod(ShipmentMethodTransfer $methodTransfer)
    {
        $methodModel = $this->getFactory()->createMethodReader();

        return $methodModel->create($methodTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param int $idShipmentMethod
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer|null
     */
    public function findMethodById($idShipmentMethod)
    {
        return $this->getFactory()
            ->createMethodReader()
            ->findShipmentMethodTransferById($idShipmentMethod);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @deprecated Use getAvailableMethodsByShipment() instead.
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodsTransfer
     */
    public function getAvailableMethods(QuoteTransfer $quoteTransfer)
    {
        return $this->getFactory()
            ->createMethod()
            ->getAvailableShipmentMethods($quoteTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentGroupCollectionTransfer
     */
    public function getAvailableMethodsByShipment(QuoteTransfer $quoteTransfer): ShipmentGroupCollectionTransfer
    {
        return $this
            ->getFactory()
            ->createMethodReader()
            ->getAvailableMethodsByShipment($quoteTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param int $idShipmentMethod
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer|null
     */
    public function findAvailableMethodById($idShipmentMethod, QuoteTransfer $quoteTransfer)
    {
        return $this->getFactory()
            ->createMethodReader()
            ->findAvailableMethodById($idShipmentMethod, $quoteTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param int $idMethod
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer
     */
    public function getShipmentMethodTransferById($idMethod)
    {
        $methodModel = $this->getFactory()->createMethodReader();

        return $methodModel->getShipmentMethodTransferById($idMethod);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param int $idMethod
     *
     * @return bool
     */
    public function hasMethod($idMethod)
    {
        $methodModel = $this->getFactory()->createMethodReader();

        return $methodModel->hasMethod($idMethod);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param int $idMethod
     *
     * @return bool
     */
    public function deleteMethod($idMethod)
    {
        $methodModel = $this->getFactory()->createMethodReader();

        return $methodModel->deleteMethod($idMethod);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $methodTransfer
     *
     * @return int|bool
     */
    public function updateMethod(ShipmentMethodTransfer $methodTransfer)
    {
        $methodModel = $this->getFactory()->createMethodReader();

        return $methodModel->updateMethod($methodTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return void
     */
    public function calculateShipmentTaxRate(QuoteTransfer $quoteTransfer)
    {
        $this->getFactory()
            ->createShipmentTaxCalculatorStrategyResolver()
            ->resolve($quoteTransfer->getItems())
            ->recalculate($quoteTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @deprecated Use saveOrderShipment() instead
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return void
     */
    public function saveShipmentForOrder(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponse)
    {
        $this->getFactory()
            ->createShipmentOrderSaver()
            ->saveShipmentForOrder($quoteTransfer, $checkoutResponse);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\SaveOrderTransfer $saveOrderTransfer
     *
     * @return void
     */
    public function saveOrderShipment(QuoteTransfer $quoteTransfer, SaveOrderTransfer $saveOrderTransfer)
    {
        $this->getFactory()
            ->createCheckoutShipmentOrderSaverStrategyResolver()
            ->resolve($quoteTransfer->getItems())
            ->saveOrderShipment($quoteTransfer, $saveOrderTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    public function hydrateOrderShipment(OrderTransfer $orderTransfer)
    {
        return $this->getFactory()->createMultipleShipmentOrderHydrate()->hydrateOrderWithShipment($orderTransfer);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $shipmentMethodEntity
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer
     */
    public function transformShipmentMethodEntityToShipmentMethodTransfer(SpyShipmentMethod $shipmentMethodEntity)
    {
        return $this->getFactory()->createShipmentMethodTransformer()
            ->transformEntityToTransfer($shipmentMethodEntity);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param int $idShipmentMethod
     *
     * @return bool
     */
    public function isShipmentMethodActive($idShipmentMethod)
    {
        return $this->getFactory()->createMethodReader()->isShipmentMethodActive($idShipmentMethod);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @return string
     */
    public function getShipmentExpenseTypeIdentifier()
    {
        return ShipmentConstants::SHIPMENT_EXPENSE_TYPE;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param int $idSalesShipment
     *
     * @return \Generated\Shared\Transfer\ShipmentTransfer|null
     */
    public function findShipmentTransferById(int $idSalesShipment): ?ShipmentTransfer
    {
        return $this->getFactory()
            ->createShipmentReader()
            ->findShipmentById($idSalesShipment);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ShipmentGroupTransfer $shipmentGroupTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentGroupResponseTransfer
     */
    public function saveShipment(ShipmentGroupTransfer $shipmentGroupTransfer, OrderTransfer $orderTransfer): ShipmentGroupResponseTransfer
    {
        return $this->getFactory()
            ->createShipmentSaver()
            ->saveShipment($shipmentGroupTransfer, $orderTransfer);
    }
}
