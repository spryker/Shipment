<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Business\Model;

use Generated\Shared\Transfer\QuoteTransfer;
use Generated\Shared\Transfer\ShipmentMethodsTransfer;
use Generated\Shared\Transfer\ShipmentMethodTransfer;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethod;
use Spryker\Shared\Price\PriceMode;
use Spryker\Zed\Shipment\Dependency\Facade\ShipmentToCurrencyInterface;
use Spryker\Zed\Shipment\Dependency\Facade\ShipmentToStoreInterface;
use Spryker\Zed\Shipment\Persistence\ShipmentQueryContainerInterface;
use Spryker\Zed\Shipment\ShipmentDependencyProvider;

class Method implements MethodInterface
{

    /**
     * @var \Spryker\Zed\Shipment\Persistence\ShipmentQueryContainerInterface
     */
    protected $queryContainer;

    /**
     * @var \Spryker\Zed\Shipment\Business\Model\MethodPriceInterface
     */
    protected $methodPrice;

    /**
     * @var \Spryker\Zed\Shipment\Dependency\Facade\ShipmentToCurrencyInterface
     */
    protected $currencyFacade;

    /**
     * @var \Spryker\Zed\Shipment\Dependency\Facade\ShipmentToStoreInterface
     */
    protected $storeFacade;

    /**
     * @var array
     */
    protected $plugins;

    /**
     * @var array Keys are currency iso codes, values are Currency transfer objects.
     */
    protected $currencyCache = [];

    /**
     * @param \Spryker\Zed\Shipment\Persistence\ShipmentQueryContainerInterface $queryContainer
     * @param \Spryker\Zed\Shipment\Business\Model\MethodPriceInterface $methodPrice
     * @param \Spryker\Zed\Shipment\Dependency\Facade\ShipmentToCurrencyInterface $currencyFacade
     * @param \Spryker\Zed\Shipment\Dependency\Facade\ShipmentToStoreInterface $storeFacade
     * @param array $plugins
     */
    public function __construct(ShipmentQueryContainerInterface $queryContainer, MethodPriceInterface $methodPrice, ShipmentToCurrencyInterface $currencyFacade, ShipmentToStoreInterface $storeFacade, array $plugins)
    {
        $this->queryContainer = $queryContainer;
        $this->methodPrice = $methodPrice;
        $this->currencyFacade = $currencyFacade;
        $this->storeFacade = $storeFacade;
        $this->plugins = $plugins;
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $methodTransfer
     *
     * @return int
     */
    public function create(ShipmentMethodTransfer $methodTransfer)
    {
        $methodEntity = new SpyShipmentMethod();
        $methodEntity->fromArray($methodTransfer->toArray());
        $methodEntity->save();

        $idShipmentMethod = $methodEntity->getPrimaryKey();
        $methodTransfer->setIdShipmentMethod($idShipmentMethod);
        $this->methodPrice->save($methodTransfer);

        return $idShipmentMethod;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodsTransfer
     */
    public function getAvailableMethods(QuoteTransfer $quoteTransfer)
    {
        $shipmentMethodsTransfer = new ShipmentMethodsTransfer();
        $methods = $this->queryContainer->queryActiveMethods()->find();
        $currentStoreId = $this->storeFacade->getCurrentStore()->getIdStore();

        foreach ($methods as $shipmentMethodEntity) {
            $shipmentMethodTransfer = $this->findAvailableMethod($shipmentMethodEntity, $quoteTransfer, $currentStoreId);
            if ($shipmentMethodTransfer === null) {
                continue;
            }

            $shipmentMethodsTransfer->addMethod($shipmentMethodTransfer);
        }

        return $shipmentMethodsTransfer;
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $shipmentMethodEntity
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param int $currentIdStore
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer|null
     */
    protected function findAvailableMethod(SpyShipmentMethod $shipmentMethodEntity, QuoteTransfer $quoteTransfer, $currentIdStore)
    {
        if (!$this->isAvailable($shipmentMethodEntity, $quoteTransfer)) {
            return null;
        }

        $storeCurrencyPrice = $this->findStoreCurrencyPrice($shipmentMethodEntity, $quoteTransfer, $currentIdStore);
        if ($storeCurrencyPrice === null) {
            return null;
        }

        return $this->mapEntityToTransfer($shipmentMethodEntity, new ShipmentMethodTransfer())
            ->setTaxRate($this->getEffectiveTaxRate($shipmentMethodEntity))
            ->setStoreCurrencyPrice($storeCurrencyPrice)
            ->setDeliveryTime($this->getDeliveryTime($shipmentMethodEntity, $quoteTransfer))
            ->setCarrierName($this->findShipmentCarrierName($shipmentMethodEntity));
    }

    /**
     * @param int $idMethod
     *
     * @return bool
     */
    public function hasMethod($idMethod)
    {
        $methodQuery = $this->queryContainer->queryMethodByIdMethod($idMethod);

        return $methodQuery->count() > 0;
    }

    /**
     * @param int $idMethod
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer
     */
    public function getShipmentMethodTransferById($idMethod)
    {
        $shipmentMethodTransfer = new ShipmentMethodTransfer();

        $methodQuery = $this->queryContainer->queryMethodByIdMethod($idMethod);
        $shipmentMethodTransferEntity = $methodQuery->findOne();

        $shipmentMethodTransfer = $this->mapEntityToTransfer($shipmentMethodTransferEntity, $shipmentMethodTransfer);

        return $shipmentMethodTransfer;
    }

    /**
     * @param int $idShipmentMethod
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer|null
     */
    public function findShipmentMethodTransferById($idShipmentMethod)
    {
        $shipmentMethodEntity = $this->queryContainer
            ->queryMethodByIdMethod($idShipmentMethod)
            ->findOne();

        if (!$shipmentMethodEntity) {
            return null;
        }

        $shipmentMethodTransfer = new ShipmentMethodTransfer();
        $shipmentMethodTransfer = $this->mapEntityToTransfer($shipmentMethodEntity, $shipmentMethodTransfer);

        return $shipmentMethodTransfer;
    }

    /**
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer[]
     */
    public function getShipmentMethodTransfers()
    {
        $shipmentMethodTransfers = [];

        $query = $this->queryContainer
            ->queryActiveMethods();

        foreach ($query->find() as $shipmentMethodEntity) {
            $shipmentMethodTransfer = new ShipmentMethodTransfer();
            $shipmentMethodTransfer = $this->mapEntityToTransfer($shipmentMethodEntity, $shipmentMethodTransfer);
            $shipmentMethodTransfers[] = $shipmentMethodTransfer;
        }

        return $shipmentMethodTransfers;
    }

    /**
     * @param int $idMethod
     *
     * @return bool
     */
    public function deleteMethod($idMethod)
    {
        $methodQuery = $this->queryContainer->queryMethodByIdMethod($idMethod);
        $entity = $methodQuery->findOne();

        if ($entity) {
            $entity->delete();
        }

        return true;
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $methodTransfer
     *
     * @return int|bool
     */
    public function updateMethod(ShipmentMethodTransfer $methodTransfer)
    {
        if ($this->hasMethod($methodTransfer->getIdShipmentMethod())) {
            $methodEntity =
                $this->queryContainer->queryMethodByIdMethod($methodTransfer->getIdShipmentMethod())->findOne();

            $methodEntity->fromArray($methodTransfer->toArray());
            $methodEntity->save();
            $this->methodPrice->save($methodTransfer);

            return $methodEntity->getPrimaryKey();
        }

        return false;
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $method
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return bool
     */
    protected function isAvailable(SpyShipmentMethod $method, QuoteTransfer $quoteTransfer)
    {
        $availabilityPlugins = $this->plugins[ShipmentDependencyProvider::AVAILABILITY_PLUGINS];
        $isAvailable = true;

        if (isset($availabilityPlugins[$method->getAvailabilityPlugin()])) {
            $availabilityPlugin = $this->getAvailabilityPlugin($method, $availabilityPlugins);
            $isAvailable = $availabilityPlugin->isAvailable($quoteTransfer);
        }

        return $isAvailable;
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $method
     * @param array $availabilityPlugins
     *
     * @return \Spryker\Zed\Shipment\Communication\Plugin\ShipmentMethodAvailabilityPluginInterface
     */
    protected function getAvailabilityPlugin(SpyShipmentMethod $method, array $availabilityPlugins)
    {
        return $availabilityPlugins[$method->getAvailabilityPlugin()];
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $method
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param int $storeId
     *
     * @return int|null
     */
    protected function findStoreCurrencyPrice(SpyShipmentMethod $method, QuoteTransfer $quoteTransfer, $storeId)
    {
        $pricePlugins = $this->plugins[ShipmentDependencyProvider::PRICE_PLUGINS];
        if (isset($pricePlugins[$method->getPricePlugin()])) {
            $pricePlugin = $this->getPricePlugin($method, $pricePlugins);
            return $pricePlugin->getPrice($quoteTransfer);
        }

        $methodPriceEntity = $this->queryContainer
            ->queryMethodPriceByShipmentMethodAndStoreCurrency(
                $method->getIdShipmentMethod(),
                $storeId,
                $this->getCurrencyTransferByIsoCode($quoteTransfer->getCurrency()->getCode())->getIdCurrency()
            )
            ->findOne();
        if ($methodPriceEntity === null) {
            return null;
        }

        $price = $quoteTransfer->getPriceMode() === PriceMode::PRICE_MODE_GROSS ?
            $methodPriceEntity->getDefaultGrossPrice() :
            $methodPriceEntity->getDefaultNetPrice();

        return $price;
    }

    /**
     * @param string $currencyIsoCode
     *
     * @return \Generated\Shared\Transfer\CurrencyTransfer
     */
    protected function getCurrencyTransferByIsoCode($currencyIsoCode)
    {
        if (!isset($this->currencyCache[$currencyIsoCode])) {
            $this->currencyCache[$currencyIsoCode] = $this->currencyFacade->fromIsoCode($currencyIsoCode);
        }

        return $this->currencyCache[$currencyIsoCode];
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $method
     * @param array $pricePlugins
     *
     * @return \Spryker\Zed\Shipment\Communication\Plugin\ShipmentMethodPricePluginInterface
     */
    protected function getPricePlugin(SpyShipmentMethod $method, array $pricePlugins)
    {
        return $pricePlugins[$method->getPricePlugin()];
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $method
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return int|null
     */
    protected function getDeliveryTime(SpyShipmentMethod $method, QuoteTransfer $quoteTransfer)
    {
        $deliveryTime = null;
        $deliveryTimePlugins = $this->plugins[ShipmentDependencyProvider::DELIVERY_TIME_PLUGINS];

        if (isset($deliveryTimePlugins[$method->getDeliveryTimePlugin()])) {
            $deliveryTimePlugin = $this->getDeliveryTimePlugin($method, $deliveryTimePlugins);
            $deliveryTime = $deliveryTimePlugin->getTime($quoteTransfer);
        }

        return $deliveryTime;
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $method
     * @param array $deliveryTimePlugins
     *
     * @return \Spryker\Zed\Shipment\Communication\Plugin\ShipmentMethodDeliveryTimePluginInterface
     */
    protected function getDeliveryTimePlugin(SpyShipmentMethod $method, array $deliveryTimePlugins)
    {
        return $deliveryTimePlugins[$method->getDeliveryTimePlugin()];
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $shipmentMethodEntity
     *
     * @return int
     */
    protected function getEffectiveTaxRate(SpyShipmentMethod $shipmentMethodEntity)
    {
        if (!$shipmentMethodEntity->getTaxSet()) {
            return 0;
        }

        $effectiveTaxRate = 0;
        foreach ($shipmentMethodEntity->getTaxSet()->getSpyTaxRates() as $taxRate) {
            $effectiveTaxRate = $taxRate->getRate();
        }

        return $effectiveTaxRate;
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $shipmentMethodEntity
     *
     * @return string|null
     */
    protected function findShipmentCarrierName(SpyShipmentMethod $shipmentMethodEntity)
    {
        if (!$shipmentMethodEntity->getShipmentCarrier()) {
            return null;
        }

        return $shipmentMethodEntity->getShipmentCarrier()->getName();
    }

    /**
     * @param \Orm\Zed\Shipment\Persistence\SpyShipmentMethod $shipmentMethodEntity
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $shipmentMethodTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer
     */
    protected function mapEntityToTransfer(SpyShipmentMethod $shipmentMethodEntity, ShipmentMethodTransfer $shipmentMethodTransfer)
    {
        $shipmentMethodTransfer->fromArray($shipmentMethodEntity->toArray(), true);
        $shipmentMethodTransfer->setCarrierName($shipmentMethodEntity->getShipmentCarrier()->getName());

        return $shipmentMethodTransfer;
    }

}
