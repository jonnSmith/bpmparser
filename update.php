<?php
#!/usr/bin/php

$lang = 'ru';

global $path;
$path = $_SERVER['DOCUMENT_ROOT'].'/bpmparser';

$limit = (isset($_REQUEST['l']) && intval($_REQUEST['l']) > 0) ? intval($_REQUEST['l']) : 300;
$offset = (isset($_REQUEST['o']) && intval($_REQUEST['o']) > 0) ? intval($_REQUEST['o']) : 0;

include($path.'/lib/collectData.php');

$collectData = new collectData();
$loginMessage = $collectData->message;

if(!$debug) {
    require $path . '/vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
    $mail = new PHPMailer;
    include($path . '/lib/drupalDataBase.php');
    $drupalDataBase = new drupalDataBase($lang);
}

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