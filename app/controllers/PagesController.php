<?php

namespace App\Controllers;

class PagesController
{

    private $errors = [];
    private $minPrices = [];
    private $names = [];

    public function __construct()
    {
        header("Content-Type: application/json; charset=utf-8");
    }

    public function offers()
    {
        $this->validateOffers();

        if ( ! empty($this->errors)) {
            $this->jsonResponse([]);
        }

        try {
            $url = $_POST['url'];
            $offers = $this->parseOffers($url);
        } catch (\Throwable $e) {
            $this->throwFailedGettingDataError($e, $url);
        }

        $this->jsonResponse($offers);
    }

    public function minPrice()
    {
        $this->validateMinPrice();

        if ( ! empty($this->errors)) {
            $this->jsonResponse([]);
        }

        $vendorName = mb_strtolower(trim($_POST['vendor']));

        foreach ($_POST['urls'] as $url) {
            try {
                $this->fillMinPrices($url, $vendorName);
            } catch (\Throwable $e) {
                $this->throwFailedGettingDataError($e, $url);
            }
        }

        $this->jsonResponse(array_values($this->minPrices));
    }

    private function throwFailedGettingDataError($e, $url)
    {
        $this->errors[] = 'Failed getting xml data by link ' . $url;
        $this->errors[] = $e->getMessage();
        $this->jsonResponse([]);
    }

    private function fillMinPrices($url, $vendorName)
    {
        $xml = $this->getXml($url);

        foreach ($xml->xpath('/yml_catalog/shop') as $element) {
            $shopName = (string)$element->name;

            foreach ($element->xpath('offers/offer') as $offer) {
                $this->setMinPrice($vendorName, $shopName, $offer);
            }
        }
    }

    private function setMinPrice($vendorName, $shopName, $offer)
    {
        $offerVendor = mb_strtolower(trim((string)$offer->vendor));

        if ($offerVendor != $vendorName) {
            return;
        }

        $vendorCode = (string)$offer->vendorCode;
        $offerName = (string)$offer->name;

        if (empty($vendorCode)) {
            $vendorCode = array_search($offerName, $this->names);

            if ( ! $vendorCode) {
                $vendorCode = uniqid();
                $this->names[$vendorCode] = $offerName;
            }
        }

        if (empty($this->minPrices[$vendorCode]) || ($this->minPrices[$vendorCode]['price'] > (float)$offer->price)) {
            $this->minPrices[$vendorCode] = [
                'price' => (float)$offer->price,
                'offer' => $offerName,
                'shop'  => $shopName,
                'currency' => (string)$offer->currencyId,
            ];
        }
    }

    private function validateMinPrice()
    {
        $this->chechVendorName();
        $urls = $_POST['urls'] ?? [];

        if (empty($urls)) {
            $this->errors[] = 'No urls passed';
        }

        foreach ($urls as $url) {
            $this->checkUrl($url);
        }
    }

    private function getXml($url)
    {
        try {
            $context = stream_context_create(array('http' => array('header' => 'Accept: application/xml')));
            $xml = file_get_contents($url, false, $context);
            return simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);
        } catch (\Throwable $e) {
            $this->errors[] = 'Failed parsing xml data by link ' . $url;
            $this->jsonResponse([]);
        }
    }

    private function parseOffers($url) 
    {
        $offers = [];
        $xml = $this->getXml($url);
        $vendorName = mb_strtolower(trim($_POST['vendor']));

        foreach ($xml->xpath('/yml_catalog/shop/offers/offer') as $offer) {
            $offerVendor = mb_strtolower(trim((string)$offer->vendor));

            if ($offerVendor == $vendorName) {
                $offers[] = trim((string)$offer->name);
            }
        }

        return $offers;
    }

    private function validateOffers() 
    {
        $url = $_POST['url'] ?? '';
        $this->chechVendorName();
        $this->checkUrl($url);
    }

    private function chechVendorName()
    {
        $vendorName = $_POST['vendor'] ?? '';

        if (empty($vendorName)) {
            $this->errors[] = 'Vendor name is empty';
        }
    }

    public function checkUrl($url)
    {
        if ( ! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->errors[] = "url is not a valid URL: {$url}";
        }
    }

    private function jsonResponse($data)
    {
        if ( ! empty($this->errors)) {
            http_response_code(400);
        }

        echo json_encode([
            'status' => empty($this->errors) ? 'success' : 'failure',
            'errors' => $this->errors,
            'data'   => $data
        ], JSON_UNESCAPED_UNICODE);

        exit();
    }

    public function median()
    {
        $list1 = $_POST['list1'] ?? [];
        $list2 = $_POST['list2'] ?? [];

        $list = array_unique(array_map('intval', array_merge($list1, $list2)));

        sort($list);
        $count = sizeof($list); 
        $index = floor($count/2);

        if ( ! $count) {
            $this->errors[] = "Empty lists";
            $this->jsonResponse([]);
        } 

        if ($count & 1) {
            $this->jsonResponse($list[$index]);
        } 

        $this->jsonResponse(($list[$index-1] + $list[$index]) / 2);
    }
}