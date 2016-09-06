<?php
#!/usr/bin/php

$lang = 'ru';
$path = $_SERVER['DOCUMENT_ROOT'];
$limit = (isset($_REQUEST['l']) && intval($_REQUEST['l']) > 0) ? intval($_REQUEST['l']) : 300;
$offset = (isset($_REQUEST['o']) && intval($_REQUEST['o']) > 0) ? intval($_REQUEST['o']) : 0;

require $path.'/vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
include($path.'/lib/collectData.php');
include($path.'/lib/drupalDataBase.php');
$collectData = new collectData();
$loginMessage = $collectData->message;
$drupalDataBase = new drupalDataBase($lang);
$mail = new PHPMailer;

$statuses = $collectData->getDataArray('StatusCollection', 'Name');

$guides = $drupalDataBase->getGuides($offset,$limit,'object',$lang);

foreach($guides as $object) {
    $CrmStatusId = $collectData->getObjectStatusByGUID($object['field_guid_value'], 'ListingCollection');
    $CrmStatus = $statuses[$CrmStatusId];
    switch ($CrmStatus) {
        case 'Активный':
            $status = 1;
            break;
        default:
            $status = 0;
            break;
    }
    if($status != $object['status']) {
        $drupalDataBase->setStatus($object['nid'], $status);
        print 'Mismatched '.$object['nid'].' updated<br/>';
    }
}

drupal_flush_all_caches();