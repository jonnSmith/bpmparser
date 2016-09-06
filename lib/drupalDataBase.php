<?php

class drupalDataBase {

    private $taxonomies_array;
    private $lang;

    public function __construct($lang) {
        $this->lang = $lang;
        $path = $_SERVER['DOCUMENT_ROOT'];
        chdir($path);
        define('DRUPAL_ROOT', getcwd());
        require_once './includes/bootstrap.inc';
        drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
        require('./includes/password.inc');
        $this->taxonomies_array = $this->getTaxonomiesArray();
    }

    public function getGuides($offset,$count,$type,$lang) {

        $query = db_select('node', 'n')
            ->fields('n', array('nid', 'status'))
            ->fields('guid', array('field_guid_value'));
        $query->condition('n.type',$type,'=');
        $query->condition('n.language',$lang,'=');
        $query->orderBy('title', 'DESC');
        if($count) {
            $query->range($offset, $count);
        }
        $query->join('field_data_field_guid', 'guid', "guid.entity_id = n.nid");
        $query->groupBy('n.nid');
        $result = $query->execute();
        $objects = array();

        while($record = $result->fetchAssoc()) {
            $objects[] = $record;
        }

        return $objects;
    }

    public function setStatus($nid, $status) {
        $query = db_update('node')
            ->fields(array(
                'status' => $status
            ))
            ->condition('nid',$nid,'=');
        $query->execute();

        $query = db_update('node_revision')
            ->fields(array(
                'status' => $status
            ));
        $query->condition('nid',$nid,'=');
        $query->execute();
    }

    public function checkData($guid, $type) {
        $query = db_select('node', 'n')
            ->fields('n', array('nid'));
        $query->condition('n.type',$type,'=');
        $query->condition('n.language',$this->lang,'=');
        $query->condition('g.field_guid_value',$guid,'=');
        $query->orderBy('title', 'DESC');
        $query->range(0,1);
        $query->leftJoin('field_data_field_guid', 'g', 'g.entity_id = n.nid');
        $query->groupBy('n.nid');
        $result = $query->execute()->fetchAssoc();

        return $result;
    }

    public function insertNode($object,$lang,$type=false) {
        $query = db_insert('node')
        ->fields(array(
            'title' => $object['title'],
            'uid' => 1,
            'status' => $object['status'],
            'created' => REQUEST_TIME,
            'changed' => REQUEST_TIME,
            'language' => $object['lang'],
            'type' => $object['type']
        ));
        $nid = $query->execute();
        $query = db_update('node');
        $query->fields(array(
            'vid' => $nid,
            'changed' => REQUEST_TIME,
        ));
        $query->condition('nid', $nid);
        $query->execute();
        $query = db_insert('node_revision')
            ->fields(array(
                'title' => $object['title'],
                'nid' => $nid,
                'vid' => $nid,
                'uid' => 1,
                'status' => 1,
                'log' => '',
                'timestamp' => REQUEST_TIME
            ));
        $query->execute();

        $this->writeField('guid', $object['guid'], $nid,'object');
        $this->writeField('number', $object['guid'], $nid,'object');
        $this->writeField('object_price', $object['price'], $nid,'object');
        $this->writeField('actual_price', $object['actual_price'], $nid,'object');
        $this->writeField('meter_price', $object['meter_price'], $nid,'object');
        $this->writeField('currency', $object['currency'], $nid,'object');
        $this->writeField('number_of_rooms', $object['number_of_rooms'], $nid,'object');
        $this->writeField('building_material', $object['building_material'], $nid,'object');
        $this->writeField('type_of_house', $object['type_of_house'], $nid,'object');
        $this->writeField('floor', $object['floor'], $nid,'object');
        $this->writeField('floors_in_building', $object['floors_in_building'], $nid,'object');
        $this->writeField('district', $object['district'], $nid,'object');
        $this->writeField('total_area', $object['total_area'], $nid,'object');
        $this->writeField('living_space', $object['living_space'], $nid,'object');
        $this->writeField('kitchen_area', $object['kitchen_area'], $nid,'object');
        $this->writeField('repairs', $object['repairs'], $nid,'object');

        $this->writeTaxonomy('category', $object['property_category'], $nid,'object');
        $this->writeTaxonomy('object_type', $object['property_type'], $nid,'object');
        $this->writeTaxonomy('object_opperation', $object['operation'], $nid,'object');

        if(!empty($object['balcony']['value'])) {
            $this->overwriteTaxonomy('object_attributes', $object['balcony'], $nid,'object');
        }
        if(!empty($object['elevator']['value'])) {
            $this->overwriteTaxonomy('object_attributes', $object['elevator'], $nid,'object');
        }

        $this->writeDescription($object['description'], $nid,'object');
        $this->writeAddress($object, $nid,'object');

        $this->setImage($object, $nid, $object['image'],'object');

        if(sizeof($object['gallery']) > 0 ) {
            $this->setGallery($object, $nid, $object['gallery'], 'object');
        }

        $name = $this->convertTitle($object['title']);
        if($type) {
            $name = $type.$name;
        }
        $query = db_insert('url_alias')
            ->fields(array(
                'source' => 'node/'.$nid,
                'alias' => $name,
                'language' => $lang
            ));
        $query->execute();

        return $nid;
    }

    public function updateNode($object,$nid) {
        $query = db_update('node')
            ->fields(array(
                'title' => $object['title'],
                'status' => $object['status'],
                'changed' => REQUEST_TIME
            ));
        $query->condition('nid',$nid,'=');
        $query->execute();

        $query = db_update('node_revision')
            ->fields(array(
                'title' => $object['title'],
                'status' => $object['status'],
                'timestamp' => REQUEST_TIME
            ));
        $query->condition('nid',$nid,'=');
        $query->execute();

        $this->writeField('guid', $object['guid'], $nid,'object');
        $this->writeField('number', $object['guid'], $nid,'object');
        $this->writeField('object_price', $object['price'], $nid,'object');
        $this->writeField('actual_price', $object['actual_price'], $nid,'object');
        $this->writeField('meter_price', $object['meter_price'], $nid,'object');
        $this->writeField('currency', $object['currency'], $nid,'object');
        $this->writeField('number_of_rooms', $object['number_of_rooms'], $nid,'object');
        $this->writeField('building_material', $object['building_material'], $nid,'object');
        $this->writeField('type_of_house', $object['type_of_house'], $nid,'object');
        $this->writeField('floor', $object['floor'], $nid,'object');
        $this->writeField('floors_in_building', $object['floors_in_building'], $nid,'object');
        $this->writeField('district', $object['district'], $nid,'object');
        $this->writeField('total_area', $object['total_area'], $nid,'object');
        $this->writeField('living_space', $object['living_space'], $nid,'object');
        $this->writeField('kitchen_area', $object['kitchen_area'], $nid,'object');
        $this->writeField('repairs', $object['repairs'], $nid,'object');

        $this->writeTaxonomy('category', $object['property_category'], $nid,'object');
        $this->writeTaxonomy('object_type', $object['property_type'], $nid,'object');
        $this->writeTaxonomy('object_opperation', $object['operation'], $nid,'object');

        if(!empty($object['balcony']['value'])) {
            $this->overwriteTaxonomy('object_attributes', $object['balcony'], $nid,'object');
        }
        if(!empty($object['elevator']['value'])) {
            $this->overwriteTaxonomy('object_attributes', $object['elevator'], $nid,'object');
        }
        if(!empty($object['address_fixed']['value'])) {
            $this->overwriteTaxonomy('object_attributes', $object['address_fixed'], $nid,'object');
        }

        $this->writeDescription($object['description'], $nid,'object');
        $this->writeAddress($object, $nid,'object');

        $this->setImage($object, $nid, $object['image'],'object');

        if(sizeof($object['gallery']) > 0 ) {
            $this->setGallery($object, $nid, $object['gallery'], 'object');
        }

        return $nid;
    }

    public function setCurrency($currency) {

        $query = db_merge('node')
            ->key(array('title' => $currency['Name']))
            ->insertFields(array(
                'title' => $currency['Name'],
                'uid' => 1,
                'status' => 1,
                'created' => REQUEST_TIME,
                'changed' => REQUEST_TIME,
                'language' => 'und',
                'type' => 'currency'
            ))
            ->updateFields(array(
                'changed' => REQUEST_TIME
            ));
        $query->execute();

        $query = db_select('node', 'n')
            ->fields('n', array('nid'));
        $query->condition('n.type','currency','=');
        $query->condition('n.title',$currency['Name']);
        $query->orderBy('title', 'DESC');
        $query->range(0,1);
        $query->groupBy('n.nid');
        $nid = $query->execute()->fetchAssoc();

        $query = db_update('node');
        $query->fields(array(
            'vid' => $nid,
            'changed' => REQUEST_TIME,
        ));
        $query->condition('nid', $nid);
        $query->execute();

        $query = db_merge('node_revision')
            ->key(array('title' => $currency['Name']))
            ->insertFields(array(
                'title' => $currency['Name'],
                'nid' => $nid,
                'vid' => $nid,
                'uid' => 1,
                'status' => 1,
                'log' => ''
            ))
            ->updateFields(array(
            'timestamp' => REQUEST_TIME
            ));
        $query->execute();

        $this->writeField('symbol', $currency['Symbol'], $nid,'currency');
        $this->writeField('shortname', $currency['ShortName'], $nid,'currency');
        $this->writeField('rate', $currency['Rate'], $nid,'currency');

        return $nid;

    }

    public function setContact($user) {

        $uid = db_select('users', 'u')
            ->fields('u', array('uid'))
            ->condition('name',$user['name'])
            ->execute()->fetchField();

        $picture = 0;

        if(!empty($user['image'])) {
            $image = $user['image'];
            $query = db_merge('file_managed')
                ->key(array('filename' => $image['filename']))
                ->insertFields(array(
                    'filename' => $image['filename'],
                    'timestamp' => REQUEST_TIME,
                    'uid' => 1,
                    'uri' => 'public:/' . $image['path'] . $image['filename'],
                    'filemime' => $image['filemime'],
                    'filesize' => $image['filesize'],
                    'status' => $user['status']
                ))
                ->updateFields(array(
                    'timestamp' => REQUEST_TIME,
                    'uri' => 'public:/' . $image['path'] . $image['filename'],
                    'filemime' => $image['filemime'],
                    'filesize' => $image['filesize'],
                    'status' => $user['status']
                ));
            $query->execute();
            $query = db_select('file_managed', 'f')->fields('f', array('fid'));
            $query->condition('filename',$image['filename'],'=');
            $picture = $query->execute()->fetchField();
        }

        if($uid) {
            $query = db_update('users')
                ->fields(array(
                    'status'=>$user['status'],
                    'picture'=>$picture,
                    'mail' => $user['mail'],
                    'status'=>$user['status']
                ));
            $query->condition('uid', $uid);
            $query->execute();

            $query = db_delete('users_roles')
                ->condition('uid',$uid)
                ->execute();

            $query = db_insert('users_roles')
                ->fields(array(
                    'uid'=>$uid,
                    'rid'=>$user['role']
                ));
            $query->execute();

            $this->writeField('user_phone',$user['phone'],$uid,'user','user');
            $this->writeField('user_cell',$user['mobilephone'],$uid,'user','user');
            $this->writeField('user_skype',$user['skype'],$uid,'user','user');
            $this->writeField('user_email',$user['mail'],$uid,'user','user');
            $this->writeField('user_fb',$user['facebook'],$uid,'user','user');
            $this->writeField('user_li',$user['linkedin'],$uid,'user','user');
            $this->writeField('user_tw',$user['twitter'],$uid,'user','user');

        } else {

            $query = db_select('users', 'u');
            $query->addExpression('MAX(uid)');
            $uid = $query->execute()->fetchField();
            $uid++;

            $password = (!empty($user['mobilephone'])) ? user_hash_password($user['mobilephone']) : user_hash_password($user['mail']);

            $query = db_insert('users')
                ->fields(array(
                    'uid' => $uid,
                    'name' => $user['name'],
                    'pass' => $password,
                    'mail' => $user['mail'],
                    'signature_format' =>'filtered_html',
                    'created' => REQUEST_TIME,
                    'status'=>$user['status'],
                    'timezone'=>'Europe/Kiev',
                    'language'=>$this->lang,
                    'picture'=>$picture,
                    'init' => $user['mail']
                ));
            $query->execute();

            $query = db_delete('users_roles')
                ->condition('uid',$uid)
                ->execute();

            $query = db_insert('users_roles')
                ->fields(array(
                    'uid'=>$uid,
                    'rid'=>$user['role']
                ));
            $query->execute();

            $this->writeField('user_phone',$user['phone'],$uid,'user','user');
            $this->writeField('user_cell',$user['mobilephone'],$uid,'user','user');
            $this->writeField('user_skype',$user['skype'],$uid,'user','user');
            $this->writeField('user_email',$user['mail'],$uid,'user','user');
            $this->writeField('user_fb',$user['facebook'],$uid,'user','user');
            $this->writeField('user_li',$user['linkedin'],$uid,'user','user');
            $this->writeField('user_tw',$user['twitter'],$uid,'user','user');

        }

        if(!empty($picture)) {
            $query = db_merge('file_usage')
                ->key(array('fid' => $picture))
                ->insertFields(array(
                    'fid'=>$picture,
                    'module'=>'user',
                    'type'=>'user',
                    'id'=>$uid,
                    'count'=>1
                ))
                ->updateFields(array(
                    'module'=>'user',
                    'type'=>'user',
                    'id'=>$uid,
                    'count'=>1
                ));
            $query->execute();
        }

        return $uid;
    }

    public function getAliases($offset,$count,$type,$lang) {

        $query = db_select('node', 'n')
            ->fields('n', array('nid','title'));
        $query->condition('n.type',$type,'=');
        $query->condition('n.language',$lang,'=');
        $query->orderBy('title', 'DESC');
        if($count) {
            $query->range($offset, $count);
        }
        $query->join('url_alias', 'a', "a.source = CONCAT('node/',n.nid)");
        $query->groupBy('n.nid');
        $result = $query->execute();
        $objects = array();

        while($record = $result->fetchAssoc()) {
            $objects[] = $record;
        }
        return $objects;
    }

    public function getEmptyAliases($offset,$count,$type,$lang) {

        $query = db_select('node', 'n')
            ->fields('n', array('nid','title'));
        $query->condition('n.type',$type,'=');
        $query->condition('n.language',$lang,'=');
        $query->isNull('a.source');
        $query->orderBy('title', 'DESC');
        if($count) {
            $query->range($offset, $count);
        }
        $query->leftJoin('url_alias', 'a', "a.source = CONCAT('node/',n.nid)");
        $query->groupBy('n.nid');
        $result = $query->execute();
        $objects = array();

        while($record = $result->fetchAssoc()) {
            $objects[] = $record;
        }
        return $objects;
    }

    public function setAlias($node,$lang,$type=false) {
        $name = $this->convertTitle($node['title']);
        if($type) {
            $name = $type.$name;
        }
        $query = db_insert('url_alias')
            ->fields(array(
                'source' => 'node/'.$node['nid'],
                'alias' => $name,
                'language' => $lang
            ));
        $query->execute();
    }

    public function updateAlias($node,$lang,$type=false) {
        $name = $this->convertTitle($node['title']);
        if($type) {
            $name = $type.$name;
        }
        $query = db_update('url_alias')
            ->fields(array(
                'alias' => $name,
                'language' => $lang
            ))
            ->condition('source','node/'.$node['nid'],'=');
        $query->execute();
    }

    private function writeField($field, $value, $nid, $bundle, $entity_type = 'node') {
        if(!empty($value)) {
            $query = db_merge('field_data_field_' . $field)
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'field_' . $field . '_value' => $value,
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0
                ))
                ->updateFields(array(
                    'field_' . $field . '_value' => $value
                ));
            $query->execute();

            $query = db_merge('field_revision_field_' . $field)
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'field_' . $field . '_value' => $value,
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0
                ))
                ->updateFields(array(
                    'field_' . $field . '_value' => $value
                ));
            $query->execute();
        }
    }

    private function writeTaxonomy($field, $value, $nid, $bundle, $entity_type = 'node') {
        if(!empty($this->taxonomies_array[$value])) {
            $query = db_merge('field_data_field_' . $field)
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'field_' . $field . '_tid' => $this->taxonomies_array[$value],
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0
                ))
                ->updateFields(array(
                    'field_' . $field . '_tid' => $this->taxonomies_array[$value]
                ));
            $query->execute();

            $query = db_merge('field_revision_field_' . $field)
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'field_' . $field . '_tid' => $this->taxonomies_array[$value],
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0
                ))
                ->updateFields(array(
                    'field_' . $field . '_tid' => $this->taxonomies_array[$value]
                ));
            $query->execute();
        }
    }

    private function overwriteTaxonomy($field, $value, $nid, $bundle, $entity_type = 'node') {

        $and =  db_and()->condition('field_' . $field . '_tid', $this->taxonomies_array[$value['name']])->condition('entity_id', $nid);

        $exist = db_select('field_data_field_' . $field, 'f')
            ->fields('f', array('entity_id'))
            ->condition($and)
            ->execute()->rowCount();

        if(!$exist) {

            $count = db_select('field_data_field_' . $field, 'f')
                ->fields('f', array('entity_id'))
                ->condition('entity_id', $nid)
                ->execute()->rowCount();

            if ($value['value'] == 'да') {
                $query = db_insert('field_data_field_' . $field)
                    ->fields(array(
                        'field_' . $field . '_tid' => $this->taxonomies_array[$value['name']],
                        'entity_type' => $entity_type,
                        'bundle' => $bundle,
                        'entity_id' => $nid,
                        'revision_id' => $nid,
                        'language' => 'und',
                        'delta' => $count
                    ));
                $query->execute();
                $query = db_insert('field_revision_field_' . $field)
                    ->fields(array(
                        'field_' . $field . '_tid' => $this->taxonomies_array[$value['name']],
                        'entity_type' => $entity_type,
                        'bundle' => $bundle,
                        'entity_id' => $nid,
                        'revision_id' => $nid,
                        'language' => 'und',
                        'delta' => $count
                    ));
                $query->execute();
            } else if ($value['value'] == 'нет' && !empty($count)) {
                $query = db_delete('field_data_field_' . $field)
                    ->condition($and)
                    ->execute();
                $query = db_delete('field_revision_field_' . $field)
                    ->condition($and)
                    ->execute();
            }
        }
    }


    private function writeDescription($value, $nid, $bundle, $entity_type = 'node') {
        if(!empty($value)) {
            $query = db_merge('field_data_body')
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'body_value' => $value,
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0
                ))
                ->updateFields(array(
                    'body_value' => $value
                ));
            $query->execute();

            $query = db_merge('field_revision_body')
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'body_value' => $value,
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0
                ))
                ->updateFields(array(
                    'body_value' => $value
                ));
            $query->execute();
        }
    }

    private function writeAddress($object, $nid, $bundle, $entity_type = 'node') {
        if(!empty($object['city']) || !empty($object['address'])) {
            $query = db_merge('field_data_field_address')
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0,
                    'field_address_country' => $object['country'],
                    'field_address_administrative_area' => '',
                    'field_address_sub_administrative_area' => '',
                    'field_address_locality' => $object['city'],
                    'field_address_dependent_locality' => '',
                    'field_address_postal_code' => $object['zip'],
                    'field_address_thoroughfare' => $object['address'],
                    'field_address_premise' => $object['district'],
                    'field_address_sub_premise' => '',
                    'field_address_organisation_name' => '',
                    'field_address_name_line' => '',
                    'field_address_first_name' => '',
                    'field_address_last_name' => '',
                    'field_address_data' => ''
                ))
                ->updateFields(array(
                    'field_address_country' => $object['country'],
                    'field_address_locality' => $object['city'],
                    'field_address_postal_code' => $object['zip'],
                    'field_address_thoroughfare' => $object['address'],
                    'field_address_premise' => $object['district']
                ));
            $query->execute();

            $query = db_merge('field_revision_field_address')
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0,
                    'field_address_country' => $object['country'],
                    'field_address_administrative_area' => '',
                    'field_address_sub_administrative_area' => '',
                    'field_address_locality' => $object['city'],
                    'field_address_dependent_locality' => '',
                    'field_address_postal_code' => $object['zip'],
                    'field_address_thoroughfare' => $object['address'],
                    'field_address_premise' => $object['district'],
                    'field_address_sub_premise' => '',
                    'field_address_organisation_name' => '',
                    'field_address_name_line' => '',
                    'field_address_first_name' => '',
                    'field_address_last_name' => '',
                    'field_address_data' => ''
                ))
                ->updateFields(array(
                    'field_address_country' => $object['country'],
                    'field_address_locality' => $object['city'],
                    'field_address_postal_code' => $object['zip'],
                    'field_address_thoroughfare' => $object['address'],
                    'field_address_premise' => $object['district']
                ));
            $query->execute();
        }
    }

    private function getTaxonomiesArray() {
        $taxonomies = array();
        $query = db_select('taxonomy_term_data', 't')
            ->fields('t', array('tid', 'name'));
        $or = db_or();
        $or->condition('t.language', $this->lang , '=');
        $or->condition('t.language', 'und' , '=');
        $query->condition($or);
        $result = $query->execute();
        while($tax = $result->fetchAssoc()) {
            $taxonomies[$tax['name']] = $tax['tid'];
        }
        return $taxonomies;
    }


    private function setImage($object,$nid,$image,$bundle, $entity_type = 'node') {
        if (!empty($image) && sizeof($image) > 0) {

            $query = db_merge('file_managed')
                ->key(array('filename' => $image['filename']))
                ->insertFields(array(
                    'filename' => $image['filename'],
                    'timestamp' => REQUEST_TIME,
                    'uid' => 1,
                    'uri' => 'public:/' . $image['path'] . $image['filename'],
                    'filemime' => $image['filemime'],
                    'filesize' => $image['filesize'],
                    'status' => $object['status'],
                    'type' => 'image'
                ))
                ->updateFields(array(
                    'timestamp' => REQUEST_TIME,
                    'uri' => 'public:/' . $image['path'] . $image['filename'],
                    'filemime' => $image['filemime'],
                    'filesize' => $image['filesize'],
                    'status' => $object['status'],
                    'type' => 'image'
                ));
            $query->execute();

            $query = db_select('file_managed', 'f')->fields('f', array('fid'));
            $query->condition('filename',$image['filename'],'=');
            $fid = $query->execute()->fetchField();



            $query = db_merge('file_usage')
                ->key(array('id' => $nid,'fid'=>$fid))
                ->insertFields(array(
                    'fid'=>$fid,
                    'module'=>'file',
                    'type'=>$entity_type,
                    'id'=>$nid,
                    'count'=>1
                ))
                ->updateFields(array(
                    'module'=>'file',
                    'type'=>'node',
                    'fid'=>$fid,
                    'count'=>1
                ));
            $query->execute();

            $query = db_merge('field_data_field_object_picture')
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'entity_type' => $entity_type,
                    'bundle' =>$bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0,
                    'field_object_picture_fid' => $fid,
                    'field_object_picture_alt' => $object['title'],
                    'field_object_picture_title' => $object['title'],
                    'field_object_picture_width' => $image['width'],
                    'field_object_picture_height' => $image['height']
                ))
                ->updateFields(array(
                    'field_object_picture_fid' => $fid,
                    'field_object_picture_alt' => $object['title'],
                    'field_object_picture_title' => $object['title'],
                    'field_object_picture_width' => $image['width'],
                    'field_object_picture_height' => $image['height']
                ));
            $query->execute();

            $query = db_merge('field_revision_field_object_picture')
                ->key(array('entity_id' => $nid))
                ->insertFields(array(
                    'entity_type' => $entity_type,
                    'bundle' => $bundle,
                    'entity_id' => $nid,
                    'revision_id' => $nid,
                    'language' => 'und',
                    'delta' => 0,
                    'field_object_picture_fid' => $fid,
                    'field_object_picture_alt' => $object['title'],
                    'field_object_picture_title' => $object['title'],
                    'field_object_picture_width' => $image['width'],
                    'field_object_picture_height' => $image['height']
                ))
                ->updateFields(array(
                    'field_object_picture_fid' => $fid,
                    'field_object_picture_alt' => $object['title'],
                    'field_object_picture_title' => $object['title'],
                    'field_object_picture_width' => $image['width'],
                    'field_object_picture_height' => $image['height']
                ));
            $query->execute();

        }
    }

    private function setGallery($object,$nid,$gallery,$bundle, $entity_type = 'node') {
        foreach($gallery as $image) {
            if (!empty($image) && sizeof($image) > 0) {

                $query = db_merge('file_managed')
                    ->key(array('filename' => $image['filename']))
                    ->insertFields(array(
                        'filename' => $image['filename'],
                        'timestamp' => REQUEST_TIME,
                        'uid' => 1,
                        'uri' => 'public:/' . $image['path'] . $image['filename'],
                        'filemime' => $image['filemime'],
                        'filesize' => $image['filesize'],
                        'status' => $object['status'],
                        'type' => 'image'
                    ))
                    ->updateFields(array(
                        'timestamp' => REQUEST_TIME,
                        'uri' => 'public:/' . $image['path'] . $image['filename'],
                        'filemime' => $image['filemime'],
                        'filesize' => $image['filesize'],
                        'status' => $object['status'],
                        'type' => 'image'
                    ));
                $query->execute();

                $query = db_select('file_managed', 'f')->fields('f', array('fid'));
                $query->condition('filename', $image['filename'], '=');
                $fid = $query->execute()->fetchField();

                $query = db_merge('file_usage')
                    ->key(array('id' => $nid,'fid' => $fid))
                    ->insertFields(array(
                        'fid' => $fid,
                        'module' => 'file',
                        'type' => $entity_type,
                        'id' => $nid,
                        'count' => 1
                    ))
                    ->updateFields(array(
                        'module' => 'file',
                        'type' => 'node',
                        'id' => $nid,
                        'count' => 1
                    ));
                $query->execute();

                $and =  db_and()->condition('field_gallery_fid', $fid)->condition('entity_id', $nid);

                $exist = db_select('field_data_field_gallery', 'f')
                    ->fields('f', array('entity_id'))
                    ->condition($and)
                    ->execute()->rowCount();

                if(!$exist) {

                    $count = db_select('field_data_field_gallery', 'f')
                        ->fields('f', array('entity_id'))
                        ->condition('entity_id', $nid)
                        ->execute()->rowCount();

                    $query = db_insert('field_data_field_gallery')
                        ->fields(array(
                            'entity_type' => $entity_type,
                            'bundle' => $bundle,
                            'entity_id' => $nid,
                            'revision_id' => $nid,
                            'language' => 'und',
                            'delta' => $count,
                            'field_gallery_fid' => $fid,
                            'field_gallery_alt' => $object['title'],
                            'field_gallery_title' => $object['title'],
                            'field_gallery_width' => $image['width'],
                            'field_gallery_height' => $image['height']
                        ));
                    $query->execute();

                    $query = db_insert('field_revision_field_gallery')
                        ->fields(array(
                            'entity_type' => $entity_type,
                            'bundle' => $bundle,
                            'entity_id' => $nid,
                            'revision_id' => $nid,
                            'language' => 'und',
                            'delta' => $count,
                            'field_gallery_fid' => $fid,
                            'field_gallery_alt' => $object['title'],
                            'field_gallery_title' => $object['title'],
                            'field_gallery_width' => $image['width'],
                            'field_gallery_height' => $image['height']
                        ));
                    $query->execute();
                }
            }
        }
    }

    private function convertTitle($title) {
        $iso = array(
            "Є"=>"YE","І"=>"I","Ѓ"=>"G","і"=>"i","№"=>"#","є"=>"ye","ѓ"=>"g",
            "А"=>"A","Б"=>"B","В"=>"V","Г"=>"G","Д"=>"D",
            "Е"=>"E","Ё"=>"YO","Ж"=>"ZH",
            "З"=>"Z","И"=>"I","Й"=>"J","К"=>"K","Л"=>"L",
            "М"=>"M","Н"=>"N","О"=>"O","П"=>"P","Р"=>"R",
            "С"=>"S","Т"=>"T","У"=>"U","Ф"=>"F","Х"=>"X",
            "Ц"=>"C","Ч"=>"CH","Ш"=>"SH","Щ"=>"SHH","Ъ"=>"'",
            "Ы"=>"Y","Ь"=>"","Э"=>"E","Ю"=>"YU","Я"=>"YA",
            "а"=>"a","б"=>"b","в"=>"v","г"=>"g","д"=>"d",
            "е"=>"e","ё"=>"yo","ж"=>"zh",
            "з"=>"z","и"=>"i","й"=>"j","к"=>"k","л"=>"l",
            "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
            "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"x",
            "ц"=>"c","ч"=>"ch","ш"=>"sh","щ"=>"shh","ъ"=>"",
            "ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya","«"=>"","»"=>"","—"=>"-"," "=>"-","/"=>"",","=>"","."=>""
        );
        return  strtolower(str_replace('--', '-',urlencode(preg_replace('/[^A-Za-z0-9\-]/', '',strtr($title, $iso)))));
    }

}