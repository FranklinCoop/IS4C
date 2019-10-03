<?php

namespace COREPOS\Fannie\API\jobs;
use \FannieConfig;
use \FannieDB;

/**
 * @class SqlUpdate
 *
 * Push a database update into the job queue
 *
 * Data format:
 * {
 *     'table': 'Name.of.table',
 *     'set': {
 *         'columnName': 'columnValue',
 *         'columnName': 'columnValue',
 *         ...
 *     },
 *     'where': {
 *         'columnName': 'columnValue',
 *         'columnName': 'columnValue',
 *         ...
 *     }
 * }
 *
 * Table name is required.
 * One or more name/value pairs is required in 'set'.
 * One or more name/value pairs is required in 'where'.
 */
class SqlUpdate extends Job
{
    private function validateData($dbc)
    {
        if (!isset($this->data['table'])) {
            return 'Error: no table specified' . PHP_EOL;
        }
        $table = $this->data['table'];
        if (!$dbc->tableExists($table)) {
            return 'Error: table does not exist: ' . $table . PHP_EOL;
        } 
        if (!isset($this->data['where']) || !isset($this->data['set'])) {
            return 'Error: no update provided' . PHP_EOL;
        }
        if (!is_array($this->data['where']) || !is_array($this->data['set'])) {
            return 'Error: malformed update' . PHP_EOL;
        }
        if (count($this->data['where']) == 0 || count($this->data['set']) == 0) {
            return 'Error: empty update' . PHP_EOL;
        }

        return true;
    }

    public function run()
    {
        $config = FannieConfig::factory();
        $dbc = FannieDB::get($config->get('OP_DB'));

        $valid = $this->validateData($dbc);
        if ($valid !== true) {
            echo $valid;
            return false;
        }

        $query = 'UPDATE ' . $table . ' SET ';
        $args = array();
        foreach ($this->data['set'] as $col => $val) {
            $query .= $dbc->identifierEscape($col) . '=?,';
            $args[] = $val;
        }
        $query = substr($query, 0, strlen($query)-1);
        $query .= ' WHERE 1=1 ';
        foreach ($this->data['where'] as $col => $val) {
            $query .= ' AND ' . $dbc->identifierEscape($col) . '=?,';
            $args[] = $val;
            if ($col == $val) {
                echo "Skipping update. This looks dangerous\n";
                echo "WHERE values {$col} and {$val} appear to be equal\n";
                return false;
            }
        }
        $query = substr($query, 0, strlen($query)-1);

        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);
        if ($res === false) {
            echo "Update failed" . PHP_EOL;
            echo "SQL error: " . $dbc->error() . PHP_EOL;
            echo "Input data: " . print_r($this->data, true) . PHP_EOL;
        }
    }
}

