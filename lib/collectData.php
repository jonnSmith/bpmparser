<?php

class collectData {

    private $connect;
    private $feed;
    public $parse;
    public $limit;
    public $message;

    public function __construct($limit = false) {
        global $path;
        include($path.'/vendor/simplepie/simplepie/autoloader.php');
        include($path.'/lib/bpmConnect.php');
        include('parseAtom.php');
        $this->feed = new SimplePie();
        $this->parse = new parseAtom();
        $this->connect = new bpmConnect();
        $this->message = $this->connect->getMessage();
        $this->limit = ($limit) ? $limit : 40;
    }

    public function getObjectStatusByGUID($guid, $collection) {
        $itemData = $this->connect->getFilterData("0/ServiceModel/EntityDataService.svc/".$collection, 'Id', $guid, 'guid');
        $this->feed->set_raw_data($itemData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $entries = $this->parse->getEntries($this->feed);
        return $this->parse->getProperty($entries[0], 'StatusId');
    }

    public function getDataArray($collection, $property, $limit=false) {
        $top = ($limit) ? $limit : $this->limit;
        $propertiesData = $this->connect->getData("0/ServiceModel/EntityDataService.svc/".$collection, 0,$top);
        $this->feed->set_raw_data($propertiesData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $dataEntries = $this->parse->getEntries($this->feed);
        $entries = array();
        foreach ($dataEntries as $entry) {
            $entries[$this->parse->getProperty($entry, "Id")] = $this->parse->getProperty($entry, $property);
        }
        return $entries;
    }

    public function getCurrencyArray() {
        $top =  10;
        $currencyData = $this->connect->getData("0/ServiceModel/EntityDataService.svc/CurrencyCollection", 0,$top);
        $this->feed->set_raw_data($currencyData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $currencyEntries = $this->parse->getEntries($this->feed);

        $rateData = $this->connect->getData("0/ServiceModel/EntityDataService.svc/CurrencyRateCollection", 0,$top);
        $this->feed->set_raw_data($rateData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $rateEntries = $this->parse->getEntries($this->feed);

        $currencies = array();
        foreach ($currencyEntries as $entry) {
            $currencies[$this->parse->getProperty($entry, "Id")] = array();
            $currencies[$this->parse->getProperty($entry, "Id")]['Symbol'] = $this->parse->getProperty($entry, 'Symbol');
            $currencies[$this->parse->getProperty($entry, "Id")]['Code'] = $this->parse->getProperty($entry, 'Code');
            $currencies[$this->parse->getProperty($entry, "Id")]['ShortName'] = $this->parse->getProperty($entry, 'ShortName');
            $currencies[$this->parse->getProperty($entry, "Id")]['Name'] = $this->parse->getProperty($entry, 'Name');
        }

        foreach ($rateEntries as $entry) {
            $currencies[$this->parse->getProperty($entry, "CurrencyId")]['Rate'] = $this->parse->getProperty($entry, "Rate");
        }

        return $currencies;
    }

    public function getGuidArray($collection, $property, $limit=false) {
        $top = ($limit) ? $limit : $this->limit;
        $propertiesData = $this->connect->getData("0/ServiceModel/EntityDataService.svc/".$collection, 0,$top);
        $this->feed->set_raw_data($propertiesData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $dataEntries = $this->parse->getEntries($this->feed);
        $entries = array();
        foreach ($dataEntries as $entry) {
            $entries[$this->parse->getProperty($entry, $property)] = $this->parse->getProperty($entry, "Id");
        }
        return $entries;
    }

    public function getItemID($name, $collection, $limit=false) {
        $top = ($limit) ? $limit : $this->limit;
        $propertiesData = $this->connect->getData("0/ServiceModel/EntityDataService.svc/".$collection, 0,$top);
        $this->feed->set_raw_data($propertiesData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $dataEntries = $this->parse->getEntries($this->feed);
        $id = 0;
        foreach ($dataEntries as $entry) {
            if($this->parse->getProperty($entry, "Name") == $name) $id = $this->parse->getProperty($entry, "Id");
        }
        return $id;
    }

    public function getObjectListing($offset, $limit=false,$orderby='') {
        $top = ($limit) ? $limit : $this->limit;
        $propertiesData = $this->connect->getData("0/ServiceModel/EntityDataService.svc/ListingCollection",$offset,$top,$orderby);
        $this->feed->set_raw_data($propertiesData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $entries = $this->parse->getEntries($this->feed);
        return $entries;
    }

    public function getObjectByGUID($guid, $collection) {
        $itemData = $this->connect->getFilterData("0/ServiceModel/EntityDataService.svc/".$collection, 'Id', $guid, 'guid');
        $this->feed->set_raw_data($itemData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $entries = $this->parse->getEntries($this->feed);
        return $entries;
    }

    public function getItemByGUID($guid, $collection) {
        $itemData = $this->connect->getFilterData("0/ServiceModel/EntityDataService.svc/".$collection, 'Id', $guid, 'guid');
        $this->feed->set_raw_data($itemData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $entries = $this->parse->getEntries($this->feed);
        return $this->parse->getProperty($entries[0], 'Name');
    }

    public function getArrayByProperty($collection, $property, $value, $type) {
        $itemData = $this->connect->getFilterData("0/ServiceModel/EntityDataService.svc/".$collection, $property, $value, $type);
        $this->feed->set_raw_data($itemData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $entries = $this->parse->getEntries($this->feed);
        return $entries;
    }

    public function getContactsByAccount($name) {
        $accountIdData = $this->connect->getFilterData("0/ServiceModel/EntityDataService.svc/AccountCollection", 'Name', urlencode($name), '');
        $this->feed->set_raw_data($accountIdData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $accountEntries = $this->parse->getEntries($this->feed);
        $accountGUID = $this->parse->getProperty($accountEntries[0], 'Id');
        $contactData = $this->connect->getFilterData("0/ServiceModel/EntityDataService.svc/ContactCollection", 'Account/Id', $accountGUID, 'guid');
        $this->feed->set_raw_data($contactData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $entries = $this->parse->getEntries($this->feed);
        return $entries;
    }

    public function getCareerDueDate($contactId) {
        $contactData = $this->connect->getFilterEntry("0/ServiceModel/EntityDataService.svc/ContactCareerCollection", 'Contact/Id', $contactId, 'guid');
        $this->feed->set_raw_data($contactData);
        $this->feed->init();
        $this->feed->handle_content_type();
        $entries = $this->parse->getEntries($this->feed);
        $dueDate = '';
        foreach ($entries as $entry) {
            $dueDate = $this->parse->getProperty($entry, "DueDate");
        }
        return $dueDate;
    }

    public function getImage($guid, $path) {
        global $imagepath;
        $image = $this->connect->getImageFile("0/img/entity/hash/SysImage/Data/".$guid);
        $filepath = $imagepath.$path.$guid.'.jpg';
        $fp = fopen($filepath, 'w');
        fwrite($fp, $image);
        fclose($fp);
        $size = getimagesize($filepath);
        $file = array();
        if(!empty($size['mime'])) {
            $file['path'] = $path;
            $file['filename'] = $guid . '.jpg';
            $file['filemime'] = $size['mime'];
            $file['filesize'] = filesize($filepath);
            $file['width'] = $size[0];
            $file['height'] = $size[1];
        }
        return $file;
    }

    public function getGalleryImage($guid, $path) {
        $image = $this->connect->getImageFile("0/img/entity/hash/ListingGalleryImage/Data/".$guid);
        global $imagepath;
        $filepath = $imagepath.$path.$guid.'.jpg';
        $fp = fopen($filepath, 'w');
        fwrite($fp, $image);
        fclose($fp);
        $size = getimagesize($filepath);
        $file = array();
        if(!empty($size['mime'])) {
            $file['path'] = $path;
            $file['filename'] = $guid . '.jpg';
            $file['filemime'] = $size['mime'];
            $file['filesize'] = filesize($filepath);
            $file['width'] = $size[0];
            $file['height'] = $size[1];
        }
        return $file;
    }

}