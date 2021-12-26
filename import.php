<?php

class Import {

    public $xmlPath = './MOSMIX_L_2018122003_01008.kml';
    public $mysqlHost = 'localhost';
    public $mysqlUsername = 'root';
    public $mysqlPassword = 'root1234';
    public $mysqlDB = 'boris';
    public $mysqlTable = 'mosmix';

    public $forecastFields = array(
                                   'elementNamePPPP',
                                   'elementNameTTT',
                                   'elementNameFF',
                                   'elementNameN',
                                   'elementNameRad1h',
                                   'elementNameDD'
                                  );
    static $connection;

    public function run()
    {
        // prepare xml
        $xml = file_get_contents($this->xmlPath);
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

        $sql = "INSERT INTO {$this->mysqlTable}"
            . "('" . join("', '", $keys) . "')"
           . " VALUES (:" . join(", :", $keys) . ")";

        $sth = $connection->prepare($sql);
        foreach ($data as $k => $v) {
            $sth->bindValue(':' . $k, $v);
        }
        $sth->execute();
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

}

$import = new Import();
$import->run();
