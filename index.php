<?php
#!/usr/bin/php

$lang = 'ru';
$link = 'property/';
$link_articles = 'article/';

global $path;
$path = $_SERVER['DOCUMENT_ROOT'].'/bpmparser';

global $imagepath;
$imagepath = $_SERVER['DOCUMENT_ROOT'].'/sites/default/files';

$hours = (isset($_REQUEST['h']) && intval($_REQUEST['h']) > 0) ? intval($_REQUEST['h']) : 3;
$limit = (isset($_REQUEST['l']) && intval($_REQUEST['l']) > 0) ? intval($_REQUEST['l']) : 10;
$offset = (isset($_REQUEST['o']) && intval($_REQUEST['o']) > 0) ? intval($_REQUEST['o']) : 0;
$get_guid = (isset($_REQUEST['guid']) && !empty($_REQUEST['guid'])) ? $_REQUEST['guid'] : false;
$debug = (isset($_REQUEST['debug']) && !empty($_REQUEST['debug'])) ? $_REQUEST['debug'] : true;
$email = (isset($_REQUEST['m']) && !empty($_REQUEST['m'])) ? $_REQUEST['m'] : 'eugenpushkaroff@gmail.com';

$parse_currency = (isset($_REQUEST['c']) && !empty($_REQUEST['c'])) ? true : true;
$parse_user = (isset($_REQUEST['u']) && !empty($_REQUEST['u'])) ? true : true;
$aliases = (isset($_REQUEST['a']) && !empty($_REQUEST['a'])) ? $_REQUEST['a'] : false;

$amenities_limit = 80;
$empty_id = '00000000-0000-0000-0000-000000000000';
$empty_date = '0001-01-01T00:00:00';

set_time_limit(3600*$hours);

$inserted = 0;
$updated = 0;

include($path.'/lib/collectData.php');

$collectData = new collectData();
$loginMessage = $collectData->message;

if(!$debug) {
    require $path . '/vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
    $mail = new PHPMailer;
    include($path . '/lib/drupalDataBase.php');
    $drupalDataBase = new drupalDataBase($lang);
}

$alias_inserted = 0;
$alias_updated = 0;

if($aliases == 'update' && !$debug) {
    $nodes = $drupalDataBase->getAliases(false,false,'object',$lang);
    foreach($nodes as $node) {
        $drupalDataBase->updateAlias($node,$lang,$link);
    }
    $articles = $drupalDataBase->getAliases(false,false,'article',$lang);
    foreach($articles as $article) {
        $drupalDataBase->updateAlias($article,$lang,$link_articles);
    }
    $alias_updated = count($nodes) + count($articles);
} elseif($aliases) {
    $nodes = $drupalDataBase->getEmptyAliases(false,false,'object',$lang);
    foreach($nodes as $node) {
        $drupalDataBase->setAlias($node,$lang,$link);
    }
    $articles = $drupalDataBase->getEmptyAliases(false,false,'article',$lang);
    foreach($articles as $article) {
        $drupalDataBase->setAlias($article,$lang,$link_articles);
    }
    $alias_inserted = count($nodes) + count($articles);
}

$statuses = $collectData->getDataArray('StatusCollection', 'Name');
$cities = $collectData->getDataArray('CityCollection', 'Name');
$countries = $collectData->getDataArray('CountryCollection', 'Name');
$categories = $collectData->getDataArray('CategoryCollection', 'Name');
$types = $collectData->getDataArray('PropertyTypeCollection', 'Name');
$operations = $collectData->getDataArray('ListingTypeCollection', 'Name');
$amenities = $collectData->getDataArray('AmenityCollection', 'Name', $amenities_limit);

$amenitiesFields = [
    "Количество комнат" => 'number_of_rooms',
    "Материал здания" => 'building_material',
    "Тип дома" => 'type_of_house',
    "Этаж" => 'floor',
    "Этажность" => 'floors_in_building',
    "Этажей в здании" => 'floors_in_building',
    "Район города" => 'district',
    "Общая площадь" => 'total_area',
    "Жилая площадь" => 'living_space',
    "Площадь кухни" => 'kitchen_area',
    "Ремонт" => 'repairs',
    "Балкон/лоджия" => 'balcony',
    "Лифт" => 'elevator',
    "Адрес уточненный" => 'address_fixed'
];
$amenitiesCheckboxes = ["Балкон/лоджия", "Лифт", "Адрес уточненный"];

$currencies = $collectData->getCurrencyArray();

if ($parse_currency && !$debug) {
    foreach ($currencies as $cur) {
        $currencyID = $drupalDataBase->setCurrency($cur);
    }
}

$contacts = $collectData->getContactsByAccount('Наша компания');

if ($parse_user && !$debug) {
    foreach ($contacts as $entry) {
        $contact = array();
        $contact['name'] = $collectData->parse->getProperty($entry, "Name");
        $contact['phone'] = $collectData->parse->getProperty($entry, "Phone");
        $contact['mobilephone'] = $collectData->parse->getProperty($entry, "MobilePhone");
        $contact['homephone'] = $collectData->parse->getProperty($entry, "HomePhone");
        $contact['facebook'] = $collectData->parse->getProperty($entry, "Facebook");
        $contact['linkedin'] = $collectData->parse->getProperty($entry, "LinkedIn");
        $contact['twitter'] = $collectData->parse->getProperty($entry, "Twitter");
        $contact['skype'] = $collectData->parse->getProperty($entry, "Skype");
        $contact['mail'] = $collectData->parse->getProperty($entry, "Email");
        $image_id = $collectData->parse->getProperty($entry, "PhotoId");
        if (!empty($image_id) && $image_id != $empty_id) {
            $image = $collectData->getImage($image_id, '/users/');
            if (!empty($image)) {
                $contact['image'] = $image;
            }
        }
        $due_date = $collectData->getCareerDueDate($collectData->parse->getProperty($entry, "Id"));
        $contact['status'] = ($due_date == $empty_date) ? 1 : 0;
        $contact['role'] = 4;
        if ($contact['mail'] || $contact['mobilephone']) {
            $contactID = $drupalDataBase->setContact($contact);
        }
    }
}

if (!$get_guid) {
    $entries = $collectData->getObjectListing($offset, $limit, '&$orderby=ModifiedOn+desc');
}
else {
    $entries = $collectData->getObjectByGUID($get_guid, 'ListingCollection');
}

foreach ($entries as $entry) {

    $object = array();
    $object['title'] = $collectData->parse->getProperty($entry, "Name");
    $object['guid'] = $collectData->parse->getProperty($entry, "Id");

    $currency = $currencies[$collectData->parse->getProperty($entry, "CurrencyId")];
    $price = $collectData->parse->getProperty($entry, "Price") / $currency['Rate'];
    $meter_price = $collectData->parse->getProperty($entry, "UsrMetrePrice") / $currency['Rate'];
    $object['actual_price'] = $collectData->parse->getProperty($entry, "Price");
    $object['price'] = $price;
    $object['meter_price'] = $meter_price;
    $object['currency'] = $currency['ShortName'];

    $object['description'] = $collectData->parse->getProperty($entry, "Description");

    $object['address'] = $collectData->parse->getProperty($entry, "Address");
    $object['street'] = $collectData->parse->getProperty($entry, "Street");
    $object['housenumber'] = $collectData->parse->getProperty($entry, "HouseNumber");
    $object['city'] = $cities[$collectData->parse->getProperty($entry, "CityId")];
    $object['country'] = $collectData->parse->getCountryCode($countries[$collectData->parse->getProperty($entry, "CountryId")]);
    $object['zip'] = ($collectData->parse->getProperty($entry, "Zip")) ? $collectData->parse->getProperty($entry, "Zip") : 0;

    $object['property_category'] = $categories[$collectData->parse->getProperty($entry, "PropertyCategoryId")];
    $object['property_type'] = $types[$collectData->parse->getProperty($entry, "PropertyTypeId")];
    $object['operation'] = $operations[$collectData->parse->getProperty($entry, "ListingTypeId")];

    $object_amenities_entries = $collectData->getArrayByProperty('AmenityInObjectCollection', 'Listing/Id', $object['guid'], 'guid');

    $object_amenities = array();

    foreach ($object_amenities_entries as $object_amenities_entry) {
        $object_amenities[$collectData->parse->getProperty($object_amenities_entry, "AmenityId")] = $collectData->parse->getProperty($object_amenities_entry, "Value");
    }

    foreach ($object_amenities as $amenity_key => $amenity_value) {
        $field_name = $amenities[$amenity_key];
        $db_filed_name = $amenitiesFields[$field_name];
        if (!empty($db_filed_name) && !empty($amenity_value)) {
            if (!in_array($field_name, $amenitiesCheckboxes)) {
                $object[$db_filed_name] = $amenity_value;
            }
            else {
                $object[$db_filed_name]['value'] = (!empty($amenity_value) && $amenity_value != 'нет') ? 'да' : 'нет';
                $object[$db_filed_name]['name'] = $field_name;
            }
        }
    }

    switch ($statuses[$collectData->parse->getProperty($entry, "StatusId")]) {
        case 'Активный':
            $object['status'] = 1;
            break;
        default:
            $object['status'] = 0;
            break;
    }

    if (!$debug) {

        $image_id = $collectData->parse->getProperty($entry, "PhotoId");

        if (!empty($image_id) && $image_id != $empty_id) {
            $image = $collectData->getImage($image_id, '/object/picture/');
            if (!empty($image)) {
                $object['image'] = $image;
            }
        }

        $object_gallery_entries = $collectData->getArrayByProperty('ListingGalleryImageCollection', 'Listing/Id', $object['guid'], 'guid');
        $i = 0;
        foreach ($object_gallery_entries as $object_gallery_entry) {
            $gid = $collectData->parse->getProperty($object_gallery_entry, "Id");
            if (!empty($gid) && $gid != $empty_id) {
                $image = $collectData->getGalleryImage($gid, '/object/gallery/');
                if (!empty($image)) {
                    $object['gallery'][$i] = $image;
                    $i++;
                }
            }
        }
    }

    $object['type'] = 'object';
    $object['lang'] = $lang;

    if (!$debug) {
        $check = $drupalDataBase->checkData($object['guid'], 'object');
        $nid = $check["nid"];

        if (!$check) {
            $objectID = $drupalDataBase->insertNode($object, $lang, $link);
            $inserted++;
        }
        else {
            $objectID = $drupalDataBase->updateNode($object, $nid);
            $updated++;
        }
    }
    else {
        var_dump($object);
    }
}

if (!$debug) {

    drupal_flush_all_caches();

    $mail->setFrom('eugenpushkaroff@gmail.com', 'BPMOnlineParser');
    $mail->addAddress($email);

    $mail->isHTML(TRUE);

    $mail->Subject = 'CRM BPMOnline Drupal Parser';
    $mail->Body = $loginMessage.' Object updated:<b>' . $updated . '</b> | object inserted:<b>' . $inserted . '</b> | limit:<b>' . $limit . '</b> | offset:<b>' . $offset . '</b>';
    $mail->AltBody = $loginMessage.'Object updated:' . $updated . ' | object inserted:' . $inserted . ' | limit:' . $limit . ' | offset:' . $offset . '';

    if($aliases) {
        $mail->Body .= ' | aliases inserted:<b>' .$alias_inserted . '</b> | aliases updated:<b>'. $alias_updated . '</b>';
        $mail->AltBody .= ' | aliases inserted: ' .$alias_inserted . ' | aliases updated:'. $alias_updated;
    }

    if (!$mail->send()) {
        print 'Message could not be sent.';
        print '<br/>';
        print 'Mailer Error: ' . $mail->ErrorInfo;
        print '<br/>';
        print $loginMessage.' Object updated:<b>' . $updated . '</b> | object inserted:<b>' . $inserted . '</b> | limit:<b>' . $limit . '</b> | offset:<b>' . $offset . '</b>';
        if($aliases) {
            print ' | aliases inserted:<b>' . $alias_inserted . '</b> | aliases updated:<b>' . $alias_updated . '</b>';
        }
    }
    else {
        print 'Message has been sent';
        print '<br/>';
        print $loginMessage.' Object updated:<b>' . $updated . '</b> | object inserted:<b>' . $inserted . '</b> | limit:<b>' . $limit . '</b> | offset:<b>' . $offset . '</b>';
        if($aliases) {
            print ' | aliases inserted:<b>' . $alias_inserted . '</b> | aliases updated:<b>' . $alias_updated . '</b>';
        }
    }

}