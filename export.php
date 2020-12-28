<?php
use Magento\Framework\App\Bootstrap;

$ini_array = parse_ini_file("settings.ini.php");
if (!$ini_array) {
	echo "Parameter file missing";
	exit(0);
}

if ($ini_array['ENABLE_ERRORS']??null) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$STORE_ID = $ini_array['STORE_ID']??null;
$ROOT_TAG = $ini_array['ROOT_TAG']??null;
$OUTPUT_FILE = $ini_array['OUTPUT_FILE']??null;
$PRODUCT_STATUS = $ini_array['PRODUCT_STATUS']??null;
$PRODUCT_VISIBILITY = $ini_array['PRODUCT_VISIBILITY']??null;
$MAGENTO_BOOTSTRAP_PATH = $ini_array['MAGENTO_BOOTSTRAP_PATH']??null;
$MANUFACTURER_ATTRIBUTES = $ini_array['MANUFACTURER_ATTRIBUTES']??null;
$AVAILABILITY_ATTRIBUTE = $ini_array['AVAILABILITY_ATTRIBUTE']??null;

if ($MANUFACTURER_ATTRIBUTES) {
	$MANUFACTURER_ATTRIBUTES = explode(',', $MANUFACTURER_ATTRIBUTES);
}

if (!$STORE_ID || !$ROOT_TAG || !$OUTPUT_FILE || !$PRODUCT_STATUS || !$PRODUCT_VISIBILITY || !$MAGENTO_BOOTSTRAP_PATH || !$AVAILABILITY_ATTRIBUTE) {
	echo "Parameter missing";
	exit(0);
}

require __DIR__ . '/' . $MAGENTO_BOOTSTRAP_PATH;

$outputFormat = '';

if ($_GET['format']??null) {
    $outputFormat = $_GET['format'];    
}
if (!in_array($outputFormat, ['skroutz', 'skroutzfile'])) {
    $outputFormat = 'csv_screen';
}

$baseNode = null;
$xml = null;
$outFile = null;
$outFileTemp = tempnam(sys_get_temp_dir(), 'mag2'.$outputFormat);
function isCsvScreen() {
    global $outputFormat;
    if ($outputFormat === 'csv_screen')
        return true;
    return false;
}
function isSkroutz() {
    global $outputFormat;
    if ($outputFormat === 'skroutz')
        return true;
    return false;
}
function isSkroutzFile() {
    global $outputFormat;
    if ($outputFormat === 'skroutzfile')
        return true;
    return false;
}


$bootstrap = Bootstrap::create(BP, $_SERVER);

$obj = $bootstrap->getObjectManager();

$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$productCollection = $obj->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
$allProducts = $productCollection->create()
            ->setStoreId($STORE_ID)
            ->addAttributeToFilter('status', $PRODUCT_STATUS)
            ->addAttributeToFilter('visibility', $PRODUCT_VISIBILITY)
            ->addAttributeToSelect('*')
            ->load();

$imageHelper = $obj->get('Magento\Catalog\Helper\Image');
$storeManager = $obj->get('Magento\Store\Model\StoreManagerInterface');   
$stockItemRepository = $obj->get('Magento\CatalogInventory\Model\Stock\StockItemRepository');
$store = $storeManager->getStore($STORE_ID); 



if (isCsvScreen()) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "aa,sku,productName,url_product,imageUrl,fullCategoryText,categoryId,weight,productStock,isInStock,availability,price,manufacturer,ean\n";
}
if (isSkroutz()) {
    header('Content-Type: text/xml; charset=utf-8');
}
if (isSkroutz() || isSkroutzFile()) {
    $outFile = $OUTPUT_FILE;

    $dom = new DomDocument("1.0", "utf-8");
    $dom->formatOutput = true;

    $root = $dom->createElement($ROOT_TAG);

    $stamp = $dom->createElement('created_at', date('Y-m-d H:i') );
    $root->appendChild($stamp);

    $nodes = $dom->createElement('products');
    $root->appendChild($nodes);

    $nameAttribute = $dom->createAttribute('name');
    $nameAttribute->value = $store->getFrontendName();
    $root->appendChild($nameAttribute);

    $urlAttribute = $dom->createAttribute('url');
    //$urlAttribute->value = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    $urlAttribute->value = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
    $root->appendChild($urlAttribute);

    $dom->appendChild($root);
    $dom->save($outFileTemp);
    
    $xml = new DOMDocument();
    $xml->formatOutput = true;
    $xml->load($outFileTemp);

    $baseNode = $xml->getElementsByTagName('products')->item(0);
}


$aa=0;
foreach ($allProducts as $product){
    $aa++;
    
    $sku = $product->getSku();

    $catIds = $product->getCategoryIds();
    $category = null;
    $categoryId = null;
    $categoryFactory = $obj->create('Magento\Catalog\Model\CategoryFactory');
    
    if ($catIds && count($catIds)) {
        $categoryId = $catIds[0];
        $category = $categoryFactory->create();
        $category->setStoreId($STORE_ID)->load($catIds[0]);        
    }
    
    try {
        $productStock = $stockItemRepository->get($product->getId()); // ayto skaei se 2-3 proionta kai giayto to try/catch
        $stockQuant = $productStock->getQty();
        $isInStock = $productStock->getIsInStock();
    }
    catch (\Exception $ex) {
        $stockQuant = 0;
        $isInStock = false;
    }
    if ($stockQuant >= 0.0 && $isInStock) {
        $inStockSkroutzValue = 'Y';
    }
    else {
        $inStockSkroutzValue = 'N';
    }    
    $price = number_format($product->getPrice(), 2, '.', '');

    $fullCategoryText = '';
    $rootCategoryId = $store->getRootCategoryId();
    if ($category) {
        $fullCategoryText = $category->getName(); 

        $productCategory = $category;
        $productCategory = $category;
        while($catParentId = $productCategory->getparent_id())
        {
            if($catParentId == $rootCategoryId) //If we reach the root node, we need to stop 
                break;
            $productCategory = $categoryFactory->create();
            $productCategory->setStoreId($STORE_ID)->load($catParentId);     
            $fullCategoryText = $productCategory->getName()." > ".$fullCategoryText;
        }
    }

    $productName = $product->getName();
    $url_product = $product->getProductUrl(false);

    $imageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product'.$product->getImage();
    $weight = $product->getResource()->getAttribute('weight')->getFrontend()->getValue($product)*1000; 
    
    $availability = $product->getAttributeText($AVAILABILITY_ATTRIBUTE);
    
    if ($MANUFACTURER_ATTRIBUTES && !is_array($MANUFACTURER_ATTRIBUTES)) {
		$MANUFACTURER_ATTRIBUTES = [$MANUFACTURER_ATTRIBUTES];
	}
	$manufacturer = '';
	if ($MANUFACTURER_ATTRIBUTES && count($MANUFACTURER_ATTRIBUTES) > 0) {
		$manufacturers = [];
		foreach($MANUFACTURER_ATTRIBUTES as $manufacturerAttribute) {
			$aManufacturer = $product->getResource()->getAttribute($manufacturerAttribute)->setStoreId($STORE_ID)->getFrontend()->getValue($product);
			if ($aManufacturer) {
				$manufacturers[] = $aManufacturer;
			}
		}
		$manufacturer = implode(' ', $manufacturers);
	}
		
    $ean=$product->getResource()->getAttribute('bar_code')->getFrontend()->getValue($product);
    $description = $product->getShortDescription();
    
    if (isCsvScreen()) {
        echo "$aa,\"$sku\",\"$productName\",\"$url_product\",\"$imageUrl\",\"$fullCategoryText\",\"$categoryId\",\"$weight\",\"$stockQuant\",\"".($isInStock===TRUE?'YES':'NO')."\",\"$availability\",\"$price\",\"$manufacturer\",\"$ean\"\n";
    }  
    if (isSkroutz() || isSkroutzFile()) {
        // check for availability of required fields
        if ($fullCategoryText) {
            $productElement = $xml->createElement("product");
            $baseNode->appendChild( $productElement );
            
            $productElement->appendChild ( $xml->createElement('id', $sku) );
            $productElement->appendChild ( $xml->createElement('name', $productName) );
            $productElement->appendChild ( $xml->createElement('url', $url_product) );
            $productElement->appendChild ( $xml->createElement('image', $imageUrl) );
            
            $categoryTag = $productElement->appendChild ( $xml->createElement('category') );
            $categoryTag->appendChild($xml->createCDATASection( $fullCategoryText ));
            
            //$descriptionTag = $productElement->appendChild ( $xml->createElement('description') );
            //$descriptionTag->appendChild($xml->createCDATASection( $description ));
    
            $productElement->appendChild ( $xml->createElement('category_id', $categoryId) );
            $productElement->appendChild ( $xml->createElement('weight', $weight) );
            $productElement->appendChild ( $xml->createElement('in_stock', $inStockSkroutzValue) );
            $productElement->appendChild ( $xml->createElement('availability', $availability) );
            $productElement->appendChild ( $xml->createElement('price', $price) );
            if ($manufacturer) {
                $manufacturerTag = $productElement->appendChild ( $xml->createElement('manufacturer') );
                $manufacturerTag->appendChild($xml->createCDATASection( $manufacturer ));
                //$productElement->appendChild ( $xml->createElement('manufacturer', $manufacturer) );
            }
            $productElement->appendChild ( $xml->createElement('ean', $ean) );
        }
    }
}  
if (isSkroutz()) {
    $xml->formatOutput = true;
    echo $xml->saveXML();    
}
if (isSkroutzFile()) {
    $xml->formatOutput = true;
    $xml->save($outFile);    
}
