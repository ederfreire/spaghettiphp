<?php

require 'lib/core/model/Connection.php';
require 'lib/core/model/Exceptions.php';

class Model {
    public $belongsTo = array();
    public $hasMany = array();
    public $hasOne = array();
    public $id;
    public $recursion = 0;
    public $schema = array();
    public $table;
    public $primaryKey;
    public $displayField;
    public $connection;
    public $order;
    public $limit;
    public $perPage = 20;
    public $validates = array();
    public $errors = array();
    public $associations = array(
        'hasMany' => array('primaryKey', 'foreignKey', 'limit', 'order'),
        'belongsTo' => array('primaryKey', 'foreignKey'),
        'hasOne' => array('primaryKey', 'foreignKey')
    );
    public $pagination = array(
        'totalRecords' => 0,
        'totalPages' => 0,
        'perPage' => 0,
        'offset' => 0,
        'page' => 0
    );
    protected $conn;
    protected static $instances = array();

    public function __construct() {
        if(!$this->connection):
            $this->connection = Config::read('App.environment');
        endif;
        if(is_null($this->table)):
            $database = Connection::getConfig($this->connection);
            $this->table = $database['prefix'] . Inflector::underscore(get_class($this));
        endif;
        $this->setSource($this->table);
        Model::$instances[get_class($this)] = $this;
        $this->createLinks();
    }
    public function __call($method, $args){
        $regex = '/(?<method>first|all|get)(?:By)?(?<complement>[a-z]+)/i';
        if(preg_match($regex, $method, $output)):
            $complement = Inflector::underscore($output['complement']);
            $conditions = explode('_and_', $complement);
            $params = array();

            if($output['method'] == 'get'):
                if(is_array($args[0])): 
                    $params['conditions'] = $args[0];
                elseif(is_numeric($args[0])):
                    $params['conditions']['id'] = $args[0];
                endif;
                
                $params['fields'][] = $conditions[0];
                $result =  $this->first($params);
                
                return $result[$conditions[0]];
            else:
                $params['conditions'] = array_combine($conditions, $args);

                return $this->$output['method']($params);
            endif;
            
        else:
            //trigger_error('Call to undefined method Model::' . $method . '()', E_USER_ERROR);
            return false;
        endif;
    }
    /**
     * @todo use static vars
     */
    public function connection() {
        if(!$this->conn):
            $this->conn = Connection::get($this->connection);
        endif;
        return $this->conn;
    }
    /**
     * @todo refactor
     */
    public function setSource($table) {
        $db = $this->connection();
        if($table):
            $this->table = $table;
            $sources = $db->listSources();
            if(!in_array($this->table, $sources)):
                throw new MissingTableException(array(
                    'table' => $this->table
                ));
                return false;
            endif;
            if(empty($this->schema)):
                $this->describe();
            endif;
        endif;
        return true;
    }
    /**
     * @todo refactor
     */
    public function describe() {
        $db = $this->connection();
        $schema = $db->describe($this->table);
        if(is_null($this->primaryKey)):
            foreach($schema as $field => $describe):
                if($describe['key'] == 'PRI'):
                    $this->primaryKey = $field;
                    break;
                endif;
            endforeach;
        endif;
        return $this->schema = $schema;
    }
    public function loadModel($model) {
        // @todo check for errors here!
        if(!array_key_exists($model, Model::$instances)):
            Model::$instances[$model] = Loader::instance('Model', $model);
        endif;
        return $this->{$model} = Model::$instances[$model];
    }
    public function createLinks() {
        foreach(array_keys($this->associations) as $type):
            $associations =& $this->{$type};
            foreach($associations as $key => $properties):
                if(is_numeric($key)):
                    unset($associations[$key]);
                    if(is_array($properties)):
                        $associations[$key = $properties['className']] = $properties;
                    else:
                        $associations[$key = $properties] = array('className' => $properties);
                    endif;
                elseif(!isset($properties['className'])):
                    $associations[$key]['className'] = $key;
                endif;
                
                $model = $associations[$key]['className'];
                if(!isset($this->{$model})):
                    $this->loadModel($model);
                endif;
                
                $associations[$key] = $this->generateAssociation($type, $associations[$key]);
            endforeach;
        endforeach;
        return true;
    }
    public function generateAssociation($type, $association) {
        foreach($this->associations[$type] as $key):
            if(!isset($association[$key])):
                $data = null;
                switch($key):
                    case 'primaryKey':
                        $data = $this->primaryKey;
                        break;
                    case 'foreignKey':
                        if($type == 'belongsTo'):
                            $data = Inflector::underscore($association['className'] . 'Id');
                        else:
                            $data = Inflector::underscore(get_class($this)) . '_' . $this->primaryKey;
                        endif;
                        break;
                    default:
                        $data = null;
                endswitch;
                $association[$key] = $data;
            endif;
        endforeach;
        return $association;
    }
    public function query($query) {
        $db = $this->connection();
        return $db->query($query);
    }
    public function fetch($query) {
        $db = $this->connection();
        return $db->fetchAll($query);
    }
    public function begin() {
        $db = $this->connection();
        return $db->begin();
    }
    public function commit() {
        $db = $this->connection();
        return $db->commit();
    }
    public function rollback() {
        $db = $this->connection();
        return $db->rollback();
    }
    public function all($params = array()) {
        $db = $this->connection();
        $params += array(
            'table' => $this->table,
            'fields' => array_keys($this->schema),
            'order' => $this->order,
            'limit' => $this->limit,
            'recursion' => $this->recursion
        );
        $results = $db->read($params);
        if($params['recursion'] >= 0):
            $results = $this->dependent($results, $params['recursion']);
        endif;
        
        return $results;
    }
    public function first($params = array()) {
        $params += array(
            'limit' => 1
        );
        $results = $this->all($params);
        
        return empty($results) ? array() : $results[0];
    }
    public function dependent($results, $recursion = 0) {
        foreach(array_keys($this->associations) as $type):
            if($recursion < 0 and ($type != 'belongsTo' && $recursion <= 0)) continue;
            foreach($this->{$type} as $name => $association):
                foreach($results as $key => $result):
                    $name = Inflector::underscore($name);
                    $model = $association['className'];
                    $params = array();
                    if($type == 'belongsTo'):
                        $params['conditions'] = array(
                            $association["primaryKey"] => $result[$association['foreignKey']]
                        );
                        $params['recursion'] = $recursion - 1;
                    else:
                        $params['conditions'] = array(
                            $association['foreignKey'] => $result[$association["primaryKey"]]
                        );
                        $params['recursion'] = $recursion - 2;
                        if($type == 'hasMany'):
                            $params['limit'] = $association['limit'];
                            $params['order'] = $association['order'];
                        endif;
                    endif;
                    $result = $this->{$model}->all($params);
                    if($type != 'hasMany' && !empty($result)):
                        $result = $result[0];
                    endif;
                    $results[$key][$name] = $result;
                endforeach;
            endforeach;
        endforeach;
        return $results;
    }
    public function count($params = array()) {
        $db = $this->connection();
        $params = array_merge($params, array(
            'fields' => '*',
            'table' => $this->table,
            'limit' => null
        ));
        return $db->count($params);
    }
    public function paginate($params = array()) {
        $params += array(
            'perPage' => $this->perPage,
            'page' => 1
        );
        $page = !$params['page'] ? 1 : $params['page'];
        $offset = ($page - 1) * $params['perPage'];
        // @todo do we really need limits and offsets together here?
        $params['limit'] = $offset . ',' . $params['perPage'];

        $totalRecords = $this->count($params);
        $this->pagination = array(
            'totalRecords' => $totalRecords,
            'totalPages' => ceil($totalRecords / $params['perPage']),
            'perPage' => $params['perPage'],
            'offset' => $offset,
            'page' => $page
        );

        return $this->all($params);
    }
    /**
     * @todo refactor. check for fields
     */
    public function toList($params = array()) {
        $params += array(
            'key' => $this->primaryKey,
            'displayField' => $this->displayField
        );
        $all = $this->all($params);
        $results = array();
        foreach($all as $result):
            $results[$result[$params['key']]] = $result[$params['displayField']];
        endforeach;
        
        return $results;
    }
    public function exists($conditions) {
        $params = array(
            'conditions' => $conditions
        );
        $row = $this->first($params);

        return !empty($row);
    }
    public function insert($data) {
        $db = $this->connection();
        $params = array(
            'values' => $data,
            'table' => $this->table
        );
        return $db->create($params);
    }
    public function update($params, $data) {
        $db = $this->connection();
        $params += array(
            'values' => $data,
            'table' => $this->table
        );
        
        return $db->update($params);
    }
    /**
     * @todo refactor
     */
    public function save($data) {
        if(isset($data[$this->primaryKey]) && !is_null($data[$this->primaryKey])):
            $this->id = $data[$this->primaryKey];
        elseif(!is_null($this->id)):
            $data[$this->primaryKey] = $this->id;
        endif;
        foreach($data as $field => $value):
            if(!isset($this->schema[$field])):
                unset($data[$field]);
            endif;
        endforeach;
        $date = date('Y-m-d H:i:s');
        if(isset($this->schema['modified']) && !isset($data['modified'])):
            $data['modified'] = $date;
        endif;
        $exists = $this->exists(array($this->primaryKey => $this->id));
        if(!$exists && isset($this->schema['created']) && !isset($data['created'])):
            $data['created'] = $date;
        endif;
        if(!($data = $this->beforeSave($data))) return false;
        if(!is_null($this->id) && $exists):
            $save = $this->update(array(
                'conditions' => array(
                    $this->primaryKey => $this->id
                ),
                'limit' => 1
            ), $data);
            $created = false;
        else:
            $save = $this->insert($data);
            $created = true;
            $this->id = $this->getInsertId();
        endif;
        $this->afterSave($created);
        return $save;
    }
    /**
     * @todo refactor
     */
    public function validate($data) {
        $this->errors = array();
        $defaults = array(
            'required' => false,
            'allowEmpty' => false,
            'message' => null
        );
        foreach($this->validates as $field => $rules):
            if(!is_array($rules) || (is_array($rules) && isset($rules['rule']))):
                $rules = array($rules);
            endif;
            foreach($rules as $rule):
                if(!is_array($rule)):
                    $rule = array('rule' => $rule);
                endif;
                $rule += $defaults;
                if($rule['allowEmpty'] && empty($data[$field])):
                    continue;
                endif;
                $required = !isset($data[$field]) && $rule['required'];
                if($required):
                    $this->errors[$field] = is_null($rule['message']) ? $rule['rule'] : $rule['message'];
                elseif(isset($data[$field])):
                    if(!$this->callValidationMethod($rule['rule'], $data[$field])):
                        $message = is_null($rule['message']) ? $rule['rule'] : $rule['message'];
                        $this->errors[$field] = $message;
                        break;
                    endif;
                endif;
            endforeach;
        endforeach;
        return empty($this->errors);
    }
    /**
     * @todo refactor
     */
    public function callValidationMethod($params, $value) {
        $method = is_array($params) ? $params[0] : $params;
        $class = method_exists($this, $method) ? $this : 'Validation';
        if(is_array($params)):
            $params[0] = $value;
            return call_user_func_array(array($class, $method), $params);
        else:
            if($class == 'Validation'):
                return Validation::$params($value);
            else:
                return $this->$params($value);
            endif;
        endif;
    }
    public function beforeSave($data) {
        return $data;
    }
    public function afterSave($created) {
        return $created;
    }
    public function delete($id, $dependent = true) {
        $params = array(
            'conditions' => array(
                $this->primaryKey => $id
            ),
            'limit' => 1
        );
        if($this->exists($id) && $this->deleteAll($params)):
            if($dependent):
                $this->deleteDependent($id);
            endif;
            return true;
        endif;
        return false;
    }
    public function deleteDependent($id) {
        foreach(array('hasOne', 'hasMany') as $type):
            foreach($this->{$type} as $model => $assoc):
                $this->{$assoc['className']}->deleteAll(array(
                    'conditions' => array(
                        $assoc['foreignKey'] => $id
                    )
                ));
            endforeach;
        endforeach;
        return true;
    }
    public function deleteAll($params = array()) {
        $db = $this->connection();
        $params += array(
            'table' => $this->table,
            'order' => $this->order,
            'limit' => $this->limit
        );
        return $db->delete($params);
    }
    public function getInsertId() {
        $db = $this->connection();
        return $db->insertId();
    }
    public function getAffectedRows() {
        $db = $this->connection();
        return $db->affectedRows();
    }
    public function escape($value) {
        $db = $this->connection();
        return $db->escape($value);
    }
}