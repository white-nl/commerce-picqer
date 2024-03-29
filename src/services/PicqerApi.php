<?php


namespace white\commerce\picqer\services;

use Craft;
use craft\base\Component;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\elements\Address;
use Picqer\Api\Client as PicqerApiClient;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\errors\PicqerApiException;
use white\commerce\picqer\models\Settings;
use yii\base\InvalidConfigException;

class PicqerApi extends Component
{
    private ?Settings $settings = null;
    
    private ?PicqerApiClient $client = null;

    public function init(): void
    {
        parent::init();

        if ($this->settings === null) {
            $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        }
    }

    /**
     * @return PicqerApiClient|null
     */
    public function getClient(): ?PicqerApiClient
    {
        if ($this->client === null) {
            $apiClient = new PicqerApiClient($this->settings->getApiDomain(), $this->settings->getApiKey());
            $apiClient->enableRetryOnRateLimitHit();
            $apiClient->setUseragent(CommercePicqerPlugin::getInstance()->description . ' (' . CommercePicqerPlugin::getInstance()->developerUrl . ')');
            
            $this->client = $apiClient;
        }
        
        return $this->client;
    }

    /**
     * @param array $filters
     * @return \Generator
     * @throws \Picqer\Api\Exception
     */
    public function getProducts(array $filters = []): \Generator
    {
        return $this->getClient()->getResultGenerator('product', $filters);
    }

    /**
     * @param string $productCode
     * @return array
     */
    public function getProductByProductCode(string $productCode): array
    {
        return $this->getClient()->getProductByProductcode($productCode);
    }

    /**
     * @param PurchasableInterface[] $purchasables
     */
    public function createMissingProducts(array $purchasables): void
    {
        foreach ($purchasables as $purchasable) {
            $result = $this->getClient()->getProducts(['productcode' => $purchasable->getSku()]);
            if (empty($result['data'])) {
                $this->getClient()->addProduct([
                    'productcode' => $purchasable->getSku(),
                    'name' => $purchasable->getDescription(),
                    'price' => $purchasable->getPrice(),
                ]);
            }
        }
    }

    /**
     * @param Order $order
     * @param bool $createMissingProducts
     * @return mixed
     * @throws PicqerApiException
     */
    public function pushOrder(Order $order, bool $createMissingProducts = false): mixed
    {
        $data = $this->buildOrderData($order);
        $data['products'] = [];
        foreach ($order->getLineItems() as $lineItem) {
            $lineData = [
                'productcode' => $lineItem->getSku(),
                'amount' => $lineItem->qty,
                'remarks' => $lineItem->note,
            ];
            
            if ($this->settings->pushPrices) {
                $lineData['price'] = $lineItem->getSalePrice();
            }

            $data['products'][] = $lineData;
        }
        
        try {
            $response = $this->getClient()->addOrder($data);
            if (!$response['success'] || !isset($response['data']['idorder'])) {
                throw new PicqerApiException($response);
            }
        } catch (PicqerApiException $e) {
            if ($e->getPicqerErrorCode() == PicqerApiException::PRODUCT_DOES_NOT_EXIST && $createMissingProducts) {
                $purchasables = [];
                foreach ($order->getLineItems() as $lineItem) {
                    if (!array_keys($purchasables, $lineItem->getSku())) {
                        $purchasables[$lineItem->getSku()] = $lineItem->getPurchasable();
                    }
                }
                $this->createMissingProducts($purchasables);
                
                return $this->pushOrder($order, false);
            } else {
                throw $e;
            }
        }
        
        return $response['data'];
    }

    /**
     * @param int $picqerOrderId
     * @param Order $order
     * @param bool $createMissingProducts
     * @return mixed
     * @throws PicqerApiException
     */
    public function updateOrder(int $picqerOrderId, Order $order, bool $createMissingProducts = false): mixed
    {
        $response = $this->getClient()->getOrder($picqerOrderId);
        if (!$response['success'] || empty($response['data']['idorder'])) {
            throw new PicqerApiException($response);
        }
        $picqerOrder = $response['data'];
        
        // Update order data
        $data = $this->buildOrderData($order);
        $orderUpdateResponse = $response = $this->getClient()->updateOrder($picqerOrderId, $data);
        if (!$response['success']) {
            throw new PicqerApiException($response);
        }

        // Check if any products have stock allocated
        $allocated = false;
        $response = $this->getClient()->getOrderProductStatus($picqerOrderId);
        if (!$response['success'] || empty($response['data']['products'])) {
            throw new PicqerApiException($response);
        }
        foreach ($response['data']['products'] as $picqerProduct) {
            if ($picqerProduct['allocated']) {
                $allocated = true;
                break;
            }
        }

        // Delete old products
        foreach ($picqerOrder['products'] as $picqerProduct) {
            $response = $this->getClient()->sendRequest('/orders/' . $picqerOrderId . '/products/' . $picqerProduct['idorder_product'], [], PicqerApiClient::METHOD_DELETE);
            if (!$response['success']) {
                throw new PicqerApiException($response);
            }
        }

        // Push new products
        foreach ($order->getLineItems() as $lineItem) {
            $response = $this->getClient()->getProducts(['productcode' => $lineItem->getSku()]);
            if (!$response['success']) {
                throw new PicqerApiException($response);
            }
            if (!empty($response['data'][0]['idproduct'])) {
                $picqerProductId = $response['data'][0]['idproduct'];
            } else {
                if (!$createMissingProducts) {
                    throw new \Exception("Product '{$lineItem->getSku()}' not found in Picqer.");
                }
                $response = $this->getClient()->addProduct([
                    'productcode' => $lineItem->getSku(),
                    'name' => $lineItem->getDescription(),
                    'price' => $lineItem->getPrice(),
                ]);
                if (!$response['success'] || empty($response['data']['idproduct'])) {
                    throw new PicqerApiException($response);
                }
                $picqerProductId = $response['data']['idproduct'];
            }

            $lineData = [
                'idproduct' => $picqerProductId,
                'amount' => $lineItem->qty,
                'remarks' => (string)$lineItem->note,
            ];

            if ($this->settings->pushPrices) {
                $lineData['price'] = $lineItem->getSalePrice();
            }

            $response = $this->getClient()->sendRequest('/orders/' . $picqerOrderId . '/products', $lineData, PicqerApiClient::METHOD_POST);
            if (!$response['success']) {
                throw new PicqerApiException($response);
            }
        }
        
        if ($allocated) {
            $this->allocateStockForOrder($picqerOrderId);
        }
        
        return $orderUpdateResponse['data'];
    }

    /**
     * @param Order $order
     * @return mixed
     * @throws PicqerApiException
     */
    public function findPicqerOrders(Order $order): mixed
    {
        $response = $this->getClient()->getOrders(['reference' => $this->composeOrderReference($order)]);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    /**
     * @param int $picqerOrderId
     * @return mixed
     * @throws PicqerApiException
     */
    public function allocateStockForOrder(int $picqerOrderId): mixed
    {
        $response = $this->getClient()->allocateStockForOrder($picqerOrderId);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }
        
        return $response['data'];
    }

    /**
     * @param int $picqerOrderId
     * @return mixed
     * @throws PicqerApiException
     */
    public function processOrder(int $picqerOrderId): mixed
    {
        $response = $this->getClient()->processOrder($picqerOrderId);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    /**
     * @param array $data
     * @return mixed
     * @throws PicqerApiException
     */
    public function createHook(array $data): mixed
    {
        $response = $this->getClient()->addHook($data);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    /**
     * @param int $id
     * @return mixed
     * @throws PicqerApiException
     */
    public function getHook(int $id): mixed
    {
        $response = $this->getClient()->getHook($id);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    /**
     * @param int $id
     * @return mixed
     * @throws PicqerApiException
     */
    public function deleteHook(int $id): mixed
    {
        $response = $this->getClient()->deleteHook($id);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    /**
     * @param Order $order
     * @return array
     * @throws InvalidConfigException
     */
    protected function buildOrderData(Order $order): array
    {
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();
        $shippingCountry = $shippingAddress?->getCountryCode();
        $billingCountry = $billingAddress?->getCountryCode();
        if ($shippingCountry) {
            $shippingCountryText = Craft::$app->getAddresses()->getCountryRepository()->get($shippingCountry)->getName();
        }
        if ($billingCountry !== $shippingCountry) {
            $billingCountryText = Craft::$app->getAddresses()->getCountryRepository()->get($billingCountry)->getName();
        }
        return [
            'reference' => $this->composeOrderReference($order),
            'emailaddress' => $order->getEmail(),

            'deliveryname' => $this->composeAddressName($shippingAddress),
            'deliverycontactname' => $this->composeAddressContactName($shippingAddress),
            'deliveryaddress' => $shippingAddress?->getAddressLine1(),
            'deliveryaddress2' => $shippingAddress?->getAddressLine2(),
            'deliveryzipcode' => $shippingAddress?->getPostalCode(),
            'deliverycity' => $shippingAddress?->getLocality(),
            'deliveryregion' => $shippingAddress?->getAdministrativeArea(),
            'deliverycountry' => $shippingCountryText ?? '',

            'invoicename' => $this->composeAddressName($billingAddress),
            'invoicecontactname' => $this->composeAddressContactName($billingAddress),
            'invoiceaddress' => $billingAddress?->getAddressLine1(),
            'invoiceaddress2' => $billingAddress?->getAddressLine2(),
            'invoicezipcode' => $billingAddress?->getPostalCode(),
            'invoicecity' => $billingAddress?->getLocality(),
            'invoiceregion' => $billingAddress?->getAdministrativeArea(),
            'invoicecountry' => $billingCountryText ?? $shippingCountryText ?? '',
        ];
    }

    /**
     * @param Order $order
     * @return string|null
     */
    protected function composeOrderReference(Order $order): ?string
    {
        return $order->reference ?: $order->number;
    }

    /**
     * @param Address|null $address
     * @return int|string|null
     */
    protected function composeAddressName(?Address $address): int|string|null
    {
        if ($address?->getOrganization()) {
            return $address->getOrganization();
        }
        
        if ($address?->fullName) {
            return $address->fullName;
        }
        
        if ($address?->firstName || $address?->lastName) {
            return trim(sprintf('%s %s', $address->firstName, $address->lastName));
        }
        
        return $address?->getId();
    }

    /**
     * @param Address|null $address
     * @return string|null
     */
    protected function composeAddressContactName(?Address $address): ?string
    {
        if ($address->getOrganization()) {
            if ($address->fullName) {
                return $address->fullName;
            }

            if ($address->firstName || $address->lastName) {
                return trim(sprintf('%s %s', $address->firstName, $address->lastName));
            }
        }
        
        return '';
    }
}
