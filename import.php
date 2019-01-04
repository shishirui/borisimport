<?php

class Import {

    public $mysqlHost = 'localhost';
    public $mysqlUsername = 'root-username';
    public $mysqlPassword = 'root-password';
    public $mysqlDB = 'boris';
    public $mysqlTable = 'mosmix';

    public $forecastFields = array('elementNamePPPP',
                                   'elementNameTTT',
                                   'elementNameFF',
                                   'elementNameN',
                                   'elementNameRad1h',
                                   'elementNameDD'
                                  );
    static $connection;

    public function run()
    {
        global $argv;

        unset($argv[0]);
        if (!$argv) {
            echo "no files\n";
            exit;
        }

        foreach ($argv as $file) {
            if (!is_file($file)) {
                echo "file not exists: $file\n";
                continue;
            }

            echo "Importing $file ...\n";
            $this->process($file);
        }
    }

    public function process($file)
    {
        // prepare xml
        $xml = file_get_contents($file);
        $xml = preg_replace('/dwd:/', '', $xml);
        $xml = preg_replace('/kml:/', '', $xml);

        // parse values
        $dom = simplexml_load_string($xml);

        $data = $dom->xpath('/kml/Document/ExtendedData/ProductDefinition');
        $result = array('Issuer' => $data[0]->Issuer->__toString(),
                        'ProductID' => $data[0]->ProductID->__toString(),
                        'GeneratingProcess' => $data[0]->GeneratingProcess->__toString(),
                        'IssueTime' => $data[0]->IssueTime->__toString(),
                        );

        $forecastTimeSteps = [];
        foreach ($data[0]->ForecastTimeSteps->TimeStep as $value) {
            $forecastTimeSteps[] = $value->__toString();
        }

        $data = $dom->xpath('/kml/Document/Placemark');
        $result['name'] = $data[0]->name->__toString();
        $result['description'] = $data[0]->description->__toString();

        $forecasts = [];
        foreach ($data[0]->ExtendedData->Forecast as $node) {
            // $node
            $elementName = $node->attributes()->elementName->__toString();
            $values = preg_split('/\s+/', trim($node->value->__toString()));
            $forecasts['elementName' . $elementName] = $values;
        }

        $data = $dom->xpath('/kml/Document/Placemark/Point');
        $result['coordinates'] = $data[0]->coordinates->__toString();

        foreach ($forecastTimeSteps as $k => $forecastTimeStep) {

            $result['ForecastTimeSteps'] = $forecastTimeStep;
            foreach ($this->forecastFields as $forecastField) {
                $result[$forecastField] = $forecasts[$forecastField][$k];
            }

            $this->insertData($result);
        }
    }
    
    public function insertData($data = array())
    {
        $keys = array_keys($data);
        $values = array_values($data);

        $this->createTableIfNotExists($keys);

        try {
            $sql = "INSERT INTO {$this->mysqlTable} "
                . "(`" . join("`, `", $keys) . "`)"
                . " VALUES (" . substr(str_repeat('?,', count($keys)), 0, -1) . ")";

            $connection = $this->getConnect();
            $sth = $connection->prepare($sql);
            $sth->execute($values);
            // print_r($sth->errorInfo());
        } catch(Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getConnect()
    {
        if (!self::$connection) {
            self::$connection = new PDO("mysql:dbname={$this->mysqlDB};host={$this->mysqlHost}",
                                        $this->mysqlUsername,
                                        $this->mysqlPassword);
        }
        return self::$connection;
    }

    public function createTableIfNotExists($fields)
    {
        try {
            $connection = $this->getConnect();
            $sth = $connection->prepare("SELECT count(*) FROM {$this->mysqlTable}");
            $sth->execute();
            $row = $sth->fetch();

            if (!$row) {
                $lines = [];
                foreach ($fields as $field) {
                    $lines[] = "`$field` VARCHAR(255) NOT NULL";
                }

                $sql = "CREATE TABLE `{$this->mysqlTable}` (" .  join(', ', $lines) . ") ENGINE = InnoDB";
                $sth = $connection->prepare($sql);
                $sth->execute();
            }
        } catch(Exception $e) {
            echo $e->getMessage();
        }
    }
}

$import = new Import();
$import->run();



