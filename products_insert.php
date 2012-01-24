#!/usr/bin/php
<?php
/* * * CONFIG ** */
$root_path = '/caminho/ate/o/magento';
require_once $root_path . 'app/Mage.php';

$dbConfig = array(
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => '',
    'driver_options' => array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8')
);

$db_magento = Zend_Db::factory('Pdo_Mysql', $dbConfig);

/* * * FUNCTIONS ** */

function AddProducts($file, $db_magento) {

    Mage::app();

    $products = file($file);
    $fields = str_getcsv($products[0]);
    unset($products[0]);

    $product = Mage::getModel('catalog/product');
    foreach ($products as $id => $line) {

        $data = dataPrepare($fields, $line);


        if (!getEntityID_bySKU($db_magento, $sku)) {

            $product = Mage::getModel('catalog/product');

            $product->setSku($data['SKU']);
            $product->setName($data['NAME']);
            $product->setDescription($data['DESCRIPTION']);            
            $product->setPrice($data['PRICE']);
            $product->setTypeId('simple');
            $product->setAttributeSetId(9); // need to look this up
            $product->setCategoryIds("20,24"); // need to look these up
//          $product->setWeight(1.0);
//          $product->setTaxClassId(2); // taxable goods
            $product->setVisibility(4); // catalog, search
            $product->setStatus(1); // enabled
  
            // assign product to the default website
            $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));

            $product->save();
        }
    }
}

function dataPrepare($fields, $line) {

    $data = str_getcsv($line);
    $data_combine = array_combine($fields, $data);

    $final_data['SKU'] = $data_combine['SkuId'];
    $final_data['NAME'] = $data_combine['Tipo'] . ' ' . $data_combine['Marca'] . ' ' . $data_combine['Produto'];
    $final_data['DESCRIPTION'] = $data_combine['Descricao'];
    $final_data['PRICE'] = $data_combine['OriginalPrice'];

    return $final_data;
}

function getEntityID_bySKU($db_magento, $sku) {

    $query = "SELECT entity_id FROM catalog_product_entity p_e WHERE p_e.sku = '$sku'";

    $entity_row = $db_magento->query($query)->fetchObject();

    if (!empty($entity_row)) {
        return $entity_row->entity_id;
        ;
    } else {
        return false;
    }
}

//  ¯\_(ツ)_/¯

$help = "\n" . 'Especifique a flag e os parametros corretamente :)' . "\n\n" .
        'products_insert.php -p /caminho/ate/o/arquivo -> Insere todos os produtos' . "\n\n";

if (isset($argv[1]) && isset($argv[2])) {

    if (!file_exists($argv[2])) {
        die("\n" . 'Arquivo "' . $argv[2] . '" não encontrado :(' . "\r\n\r\n");
    }

    if ($argv[1] == '-p') {
        AddProducts($argv[2], $db_magento);
    } else if ($argv[1] == '-h' || $argv[1] == '--help') {
        echo $help;
    } else {
        echo $help;
    }
} else {

    echo $help;
}
?>
