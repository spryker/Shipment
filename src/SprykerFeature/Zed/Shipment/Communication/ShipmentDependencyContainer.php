<?php

/**
 * (c) Spryker Systems GmbH copyright protected
 */

namespace SprykerFeature\Zed\Shipment\Communication;

use Generated\Zed\Ide\FactoryAutoCompletion\ShipmentCommunication;
use SprykerEngine\Zed\Kernel\Communication\AbstractCommunicationDependencyContainer;
use SprykerFeature\Zed\Shipment\Communication\Form\CarrierForm;
use SprykerFeature\Zed\Shipment\Communication\Table\MethodTable;
use SprykerFeature\Zed\Shipment\Persistence\ShipmentQueryContainer;

/**
 * @method ShipmentCommunication getFactory()
 * @method ShipmentQueryContainer getQueryContainer()
 */
class ShipmentDependencyContainer extends AbstractCommunicationDependencyContainer
{
    /**
     * @return MethodTable
     */
    public function createMethodTable()
    {
        $methodQuery = $this->getQueryContainer()->queryMethods();

        return $this->getFactory()->createTableMethodTable($methodQuery);
    }

    /**
     * @return CarrierForm
     */
    public function createCarrierForm()
    {
        $carrierQuery = $this->getQueryContainer()->queryCarriers();

        return $this->getFactory()->createFormCarrierForm($carrierQuery);
    }
}