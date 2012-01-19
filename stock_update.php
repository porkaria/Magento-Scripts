#!/usr/bin/php
<?php
/* ----- CONFIG ----- */
$root_path = '/caminho/ate/o/magento/';

$dbConfig = array(
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => '',
    'driver_options' => array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8')
);

$fieldsTerminated = ';';
$linesTerminated = 'n';

require_once $root_path . 'app/Mage.php';

// --external-file
$ftpConnect = array(
    'host' => 'ftp.example.com',
    'username' => 'example',
    'password' => '12345',
    'tmp_path' => '/tmp/' // vai ser usada para salvar temporariamente o arquivo baixado do ftp, precisa de permissão de escrita
);


/* ---- CONFIG ---- */



/* ---- FUNCTIONS ---- */

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

function errorLog($msg, $file, $line) {

    umask(0);
    Mage::app();

    $prefix = "\n" . $file . " (" . $line . ")\n";
    Mage::log($prefix . ' ' . $msg . "\n", null, 'scripts.log');
}

function updateStockFromFile($db_magento, $filename) {

    if (!empty($filename)) {

        $lines = file($filename);

        foreach ($lines as $line => $data) {

            $dataPrepare = dataPrepare($data);
            $productID = getEntityID_bySKU($db_magento, $dataPrepare['SKU']);

            if (!empty($productID)) {

                $query = "UPDATE cataloginventory_stock_item s_i, cataloginventory_stock_status s_s
                SET s_i.qty = '{$dataPrepare['QTD']}', s_s.qty = '{$dataPrepare['QTD']}'
                WHERE s_i.product_id = '$productID' AND s_i.product_id = s_s.product_id";


                if (!$db_magento->query($query)) {
                    errorLog('erro ao importar produto: SKU ' . $dataPrepare['SKU'] . ' Qtd ' . $dataPrepare['QTD']
                            . ' Price ' . $dataPrepare['PRICE'], __FILE__, __LINE__);
                }
            } else {
                errorLog('produto não encontrado: ' . $dataPrepare['SKU'], __FILE__, __LINE__);
            }
        }
    } else {

        errorLog('estoque não encontrado', __FILE__, __LINE__);

        return false;
    }

    return true;
}

function updatePriceFromFile($db_magento, $filename) {

    umask(0);
    Mage::app();

    if (!empty($filename)) {

        $lines = file($filename);

        foreach ($lines as $line => $data) {

            $dataPrepare = dataPrepare($data);
            $productID = getEntityID_bySKU($db_magento, $dataPrepare['SKU']);

            if (!empty($productID)) {
                $price = number_format($dataPrepare['PRICE'], 2);
                $product = Mage::getModel('catalog/product')->load($productID);
                $product->setPrice($price);
                $product->save();
            }
        }
    }
}

function updateFromFile($db_magento, $file) {

    if (!updateStockFromFile($db_magento, $file)) {
        errorLog('Erro ao importar dados de quantidade', __FILE__, __LINE__);
    }

    if (updatePriceFromFile($db_magento, $file)) {
        errorLog('Erro ao importar dados de preço', __FILE__, __LINE__);
    }
}

function dataPrepare($data) {

    $explodedData = explode(';', $data);

    return array('SKU' => $explodedData[0], 'QTD' => $explodedData[1], 'PRICE' => $explodedData[2]);
}

function openFtp($ftpConnect,$path) {

    $pathinfo = pathinfo($path);
    $remote_file = $pathinfo['filename'] . '.' . $pathinfo['extension'];
    $handle = fopen($ftpConnect['tmp_path'] . $remote_file, 'w');
    $conn_id = ftp_connect($ftpConnect['host']);
    $login_result = ftp_login($conn_id, $ftpConnect['username'], $ftpConnect['password']);

    if ((!$conn_id) || (!$login_result)) {
        die("\n" . 'FTP connection has failed' . "\n"
                . "Attempted to connect to {$ftpConnect['host']} for user {$ftpConnect['username']}" . "\n");
    }    

    if (!ftp_chdir($conn_id, $pathinfo['dirname'])) {
        die("\nFTP: Não consegui entrar nesse diretório {$pathinfo['dirname']}\n");
    }

    if (ftp_fget($conn_id, $handle, $path, FTP_ASCII, 0)) {
        echo "successfully written to {$pathinfo['filename']}\n";
    } else {
        echo "There was a problem while downloading $remote_file to $local_file\n";
    }

    ftp_close($conn_id);
    fclose($handle);

    return $ftpConnect['tmp_path'] . $remote_file;
}

/* ---- END FUNCTIONS ---- */


//  ¯\_(ツ)_/¯

$db_magento = Zend_Db::factory('Pdo_Mysql', $dbConfig);

$help = "\n" . 'Especifique a flag e os parametros corretamente :)' . "\n\n" .
        'stock_update.php -p /caminho/ate/o/arquivo -> Atualiza os preços' . "\n" .
        'stock_update.php -e /caminho/ate/o/arquivo -> Atualiza a quantidade' . "\n" .
        'stock_update.php -ep /caminho/ate/o/arquivo -> Atualiza os dois' . "\n\n" .
        'Buscando o arquivo em um servidor remoto (ftp)' . "\n\n" .
        '1) Edite o arquivo stock_update.php e insira as config do seu servidor' . "\n" .
        '2) Execute o arquivo com as flags normais adicionando --external-file no final da instrução.' . "\n" .
        'Ex: stock_update.php -ep estoque/arquivo.csv --external-file' . "\n\n" .
        'O exemplo acima vai buscar uma pasta chamada "estoque" na raiz do servidor e por fim pelo arquivo "produtos_quantidade.csv"' . "\n\n";

if (isset($argv[1]) && isset($argv[2])) {

    if (isset($argv[3]) && $argv[3] == '--external-file') {
        $remote_file = openFtp($ftpConnect,$argv[2]);
        $argv[2] = $remote_file;
    }

    if (!file_exists($argv[2])) {
        die("\n" . 'Arquivo "' . $argv[2] . '" não encontrado :(' . "\r\n\r\n");
    }

    if ($argv[1] == '-e') {
        updateStockFromFile($db_magento, $argv[2]);
    } else if ($argv[1] == '-p') {
        updatePriceFromFile($db_magento, $argv[2]);
    } else if ($argv[1] == '-ep' || $argv[1] == '-pe') {
        updateFromFile($db_magento, $argv[2]);
    } else if ($argv[1] == '-h' || $argv[1] == '--help') {
        echo $help;
    } else {
        echo $help;
    }
} else {

    echo $help;
}
?>
