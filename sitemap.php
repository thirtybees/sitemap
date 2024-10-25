<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class Sitemap
 */
class Sitemap extends Module
{
    /**
     * Hook name
     */
    const HOOK_ADD_URLS = 'gSitemapAppendUrls';

    /**
     * @var bool
     */
    public $cron = false;

    /**
     * Gsitemap constructor.
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'sitemap';
        $this->tab = 'seo';
        $this->version = '4.2.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Sitemap');
        $this->description = $this->l('Generate your sitemap file');
    }

    /**
     * Google Sitemap installation process:
     *
     * Step 1 - Pre-set Configuration option values
     * Step 2 - Install the Addon and create a database table to store Sitemap files name by shop
     *
     * @return boolean Installation result
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        foreach ([
                     'SITEMAP_PRIORITY_HOME'         => 1.0,
                     'SITEMAP_PRIORITY_PRODUCT'      => 0.9,
                     'SITEMAP_PRIORITY_CATEGORY'     => 0.8,
                     'SITEMAP_PRIORITY_MANUFACTURER' => 0.7,
                     'SITEMAP_PRIORITY_SUPPLIER'     => 0.6,
                     'SITEMAP_PRIORITY_CMS'          => 0.5,
                     'SITEMAP_FREQUENCY'             => 'weekly',
                 ] as $key => $val) {
            if (!Configuration::updateValue($key, $val)) {
                return false;
            }
        }

        return parent::install() &&
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'sitemap_sitemap` (`link` varchar(255) DEFAULT NULL, `id_shop` int(11) DEFAULT 0) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;') &&
            $this->_installHook();
    }

    /**
     * Registers hook(s)
     *
     * @return boolean
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function _installHook()
    {
        $hook = new Hook(Hook::getIdByName(static::HOOK_ADD_URLS));
        $hook->name = static::HOOK_ADD_URLS;
        $hook->title = 'GSitemap Append URLs';
        $hook->description = 'This hook allows a module to add URLs to a generated sitemap';
        $hook->position = true;

        return $hook->save();
    }

    /**
     * Google Sitemap uninstallation process:
     *
     * Step 1 - Remove Configuration option values from database
     * Step 2 - Remove the database containing the generated Sitemap files names
     * Step 3 - Uninstallation of the Addon itself
     *
     * @return boolean Uninstallation result
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        foreach ([
                     'SITEMAP_PRIORITY_HOME',
                     'SITEMAP_PRIORITY_PRODUCT',
                     'SITEMAP_PRIORITY_CATEGORY',
                     'SITEMAP_PRIORITY_MANUFACTURER',
                     'SITEMAP_PRIORITY_SUPPLIER',
                     'SITEMAP_PRIORITY_CMS',
                     'SITEMAP_FREQUENCY',
                 ] as $key) {
            if (!Configuration::deleteByName($key)) {
                return false;
            }
        }

        return parent::uninstall() && $this->removeSitemap();
    }

    /**
     * Delete all the generated Sitemap files  and drop the addon table.
     *
     * @return boolean
     * @throws PrestaShopException
     */
    public function removeSitemap()
    {
        $rootDir = $this->normalizeDirectory(_PS_ROOT_DIR_);

        // delete individual sitemap xml files
        $links = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT link FROM `'._DB_PREFIX_.'sitemap_sitemap`');
        if (is_array($links)) {
            foreach ($links as $link) {
                $filename = $rootDir . $link['link'];
                if (file_exists($filename)) {
                    @unlink($filename);
                }
            }
        }

        // delete index sitemap files
        foreach (Shop::getShops() as $shop) {
            $id = (int)$shop['id_shop'];
            $filename = $rootDir . $id . '_index_sitemap.xml';
            if (file_exists($filename)) {
                @unlink($filename);
            }
        }

        // drop database
        if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'sitemap_sitemap`')) {
            return false;
        }

        return true;
    }

    /**
     * @param $directory
     * @return string
     */
    protected function normalizeDirectory($directory)
    {
        $last = $directory[strlen($directory) - 1];

        if (in_array($last, ['/', '\\'])) {
            $directory[strlen($directory) - 1] = DIRECTORY_SEPARATOR;

            return $directory;
        }

        $directory .= DIRECTORY_SEPARATOR;

        return $directory;
    }

    /**
     * @return string
     * @throws HTMLPurifier_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        ShopUrl::resetMainDomainCache();

        $link = Context::getContext()->link;
        $shopIds = array_map('intval', Shop::getContextListShopID());
        sort($shopIds);

        $imageTypes = $this->getAllImageTypes();

        /* Store the posted parameters and generate a new Google Sitemap files for the current Shop */
        if (Tools::isSubmit('SubmitGsitemap')) {
            Configuration::updateValue('SITEMAP_FREQUENCY', Tools::getValue('sitemap_frequency'));
            Configuration::updateValue('SITEMAP_INDEX_CHECK', '');
            foreach ($imageTypes as $class => $_) {
                Configuration::updateValue('SITEMAP_IMAGE_TYPE_' . strtoupper($class), Tools::getValue($class . '_image_type'));
            }
            $meta = '';
            if (Tools::getValue('sitemap_meta')) {
                $meta .= implode(', ', Tools::getValue('sitemap_meta'));
            }
            Configuration::updateValue('SITEMAP_DISABLE_LINKS', $meta);
            foreach ($shopIds as $shopId) {
                $this->emptySitemap($shopId);
                $this->createSitemap($shopId);
            }
            $this->sitemapsGenerated($shopIds);
        } /* if no posted form and the variable [continue] is found in the HTTP request variable keep creating sitemap */
        elseif (Tools::getValue('continue')) {
            foreach ($shopIds as $shopId) {
                $this->createSitemap($shopId);
            }
            $this->sitemapsGenerated($shopIds);
        }

        $sitemaps = [];
        foreach ($shopIds as $shopId) {
            $shop = new Shop($shopId);
            $links = [];
            $rows = Db::getInstance()->executeS('SELECT link FROM `'._DB_PREFIX_.'sitemap_sitemap` WHERE id_shop = '.$shopId);
            foreach ($rows as $row) {
                $links[] = $link->getBaseLink($shopId) . $row['link'];
            }
            $sitemaps[] = [
                'shopId' => $shopId,
                'shopName' => $shop->name,
                'indexUrl'  => $link->getBaseLink($shopId) . $shopId . '_index_sitemap.xml',
                'links' => $links,
                'cronLink' => rtrim($link->getBaseLink(), '/').'/modules/sitemap/sitemap-cron.php?token='.substr(Tools::encrypt('sitemap/cron'), 0, 10).'&id_shop='.$shopId,
                'lastExport' => Configuration::getGlobalValue('SITEMAP_LAST_EXPORT_' . $shopId),
            ];
        }


        $this->context->smarty->assign(
            [
                'sitemap_form'             => './index.php?tab=AdminModules&configure=sitemap&token='.Tools::getAdminTokenLite('AdminModules').'&tab_module='.$this->tab.'&module_name=sitemap',
                'sitemap_frequency'        => Configuration::get('SITEMAP_FREQUENCY'),
                'store_metas'              => Meta::getMetasByIdLang((int) $this->context->language->id),
                'sitemap_disable_metas'    => $this->getDisabledMetas(),
                'sitemap_customer_limit'   => [
                    'max_exec_time' => (int) ini_get('max_execution_time'),
                ],
                'sitemaps'                 => $sitemaps,
                'imageTypes'               => $imageTypes,
                'selectedImageTypes'       => $this->getSelectedImageTypes(),
            ]
        );

        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    /**
     * Delete all the generated Sitemap files from the files system and the database.
     *
     * @param int $idShop
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function emptySitemap($idShop)
    {
        if (!isset($this->context)) {
            $this->context = new Context();
        }
        $this->context->shop = new Shop((int) $idShop);
        $links = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'sitemap_sitemap` WHERE id_shop = '.(int) $this->context->shop->id);
        if ($links) {
            foreach ($links as $link) {
                $filePath = $this->normalizeDirectory(_PS_ROOT_DIR_) . $link['link'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            return Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'sitemap_sitemap` WHERE id_shop = '.(int) $this->context->shop->id);
        }

        return true;
    }

    /**
     * Create the Google Sitemap by Shop
     *
     * @param int $idShop Shop identifier
     *
     * @return bool
     *
     * @throws HTMLPurifier_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function createSitemap($idShop)
    {
        $this->context->shop = new Shop((int) $idShop);
        ShopUrl::resetMainDomainCache();

        $type = Tools::getValue('type') ? Tools::getValue('type') : '';
        $languages = Language::getLanguages(true, $idShop);
        $langStop = Tools::getValue('lang') ? true : false;
        $idObj = Tools::getValue('id') ? (int) Tools::getValue('id') : 0;
        foreach ($languages as $lang) {
            $i = 0;
            $index = (Tools::getValue('index') && Tools::getValue('lang') == $lang['iso_code']) ? (int) Tools::getValue('index') : 0;
            if ($langStop && $lang['iso_code'] != Tools::getValue('lang')) {
                continue;
            } elseif ($langStop && $lang['iso_code'] == Tools::getValue('lang')) {
                $langStop = false;
            }

            // set language
            $this->context->language = new Language($lang['id_lang']);

            $linkSitemap = [];
            foreach ($this->getAllowedLinkTypes() as $typeVal) {
                if ($type == '' || $type == $typeVal) {
                    $function = '_get'.ucfirst($typeVal).'Link';
                    if (!$this->$function($linkSitemap, $lang, $index, $i, $idObj)) {
                        return false;
                    }
                    $type = '';
                    $idObj = 0;
                }
            }
            foreach($linkSitemap as $key => $link) {
                if($key!=0) {
                    if ($link['link']==$linkSitemap[0]['link']) {
                        unset($linkSitemap[$key]);
                    }
                }
            }
            $this->_recursiveSitemapCreator($linkSitemap, $lang['iso_code'], $index);
        }

        $this->_createIndexSitemap();
        Configuration::updateGlobalValue('SITEMAP_LAST_EXPORT_'.$idShop, date('r'));

        return true;
    }

    /**
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param string $lang        the language of link to add
     * @param int    $index       the index of the current Google Sitemap file
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function _recursiveSitemapCreator($linkSitemap, $lang, &$index)
    {
        if (!count($linkSitemap)) {
            return false;
        }

        $sitemapLink = $this->context->shop->id.'_'.$lang.'_'.$index.'_sitemap.xml';
        $writeFd = fopen($this->normalizeDirectory(_PS_ROOT_DIR_).$sitemapLink, 'w');

        fwrite($writeFd, (
            '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'.PHP_EOL
        ));

        foreach ($linkSitemap as $file) {
            fwrite($writeFd, '  <url>'.PHP_EOL);
            $lastModification = (isset($file['lastmod']) && !empty($file['lastmod'])) ? date('c', strtotime($file['lastmod'])) : null;
            $this->_addSitemapNode(
                $writeFd,
                $this->escapeProperty('link', $file),
                $this->_getPriorityPage($this->resolvePage($file)),
                Configuration::get('SITEMAP_FREQUENCY'),
                $lastModification
            );
            foreach ($this->getNodeImages($file) as $image) {
                $this->_addSitemapNodeImage(
                    $writeFd,
                    $this->escapeProperty('link', $image),
                    $this->escapeProperty('title_img', $image),
                    $this->escapeProperty('caption', $image)
                );
            }
            fwrite($writeFd, '  </url>'.PHP_EOL);
        }
        fwrite($writeFd, '</urlset>'.PHP_EOL);
        fclose($writeFd);
        $this->_saveSitemapLink($sitemapLink);
        $index++;

        return true;
    }

    /**
     * Return array of images in definition
     *
     * @param array $definition
     * @return array
     */
    protected function getNodeImages(array $definition)
    {
        $images = [];
        if (array_key_exists('images', $definition)) {
            $images = $definition['images'];
        }
        if (array_key_exists('image', $definition)) {
            $images[] = $definition['image'];
        }
        return array_filter($images);
    }

    /**
     * @param string $property
     * @param array $definition
     * @return string
     */
    protected function escapeProperty($property, $definition)
    {
        if (isset($definition[$property])) {
            $value = $definition[$property];
            $value = strip_tags($value);
            $value = str_replace(["\r\n", "\r", "\n"], '', $value);
            return htmlspecialchars($value);
        }
        return '';
    }

    /**
     * Add a new line to the sitemap file
     *
     * @param resource $fd       file system object resource
     * @param string   $loc      string the URL of the object page
     * @param string   $priority
     * @param string   $change_freq
     * @param int      $last_mod the last modification date/time as a timestamp
     * @throws PrestaShopException
     */
    protected function _addSitemapNode($fd, $loc, $priority, $change_freq, $last_mod = null)
    {
        fwrite($fd, (
            '    <loc>'.(Configuration::get('PS_REWRITING_SETTINGS') ? '<![CDATA['.$loc.']]>' : $loc).'</loc>'.PHP_EOL.
            '    <priority>'.number_format($priority, 1, '.', '').'</priority>'.PHP_EOL.
            '    <changefreq>'.$change_freq.'</changefreq>'.PHP_EOL
        ));

        if ($last_mod) {
            fwrite($fd, '    <lastmod>'.date('c', strtotime($last_mod)).'</lastmod>'.PHP_EOL);
        }
    }

    /**
     * return the priority value set in the configuration parameters
     *
     * @param string $page
     *
     * @return float
     * @throws PrestaShopException
     */
    protected function _getPriorityPage($page)
    {
        $priority = (float)Configuration::get('SITEMAP_PRIORITY_'.strtoupper($page));
        if (! $priority) {
            return 0.1;
        }
        return $priority;
    }

    /**
     * @param resource $fd
     * @param string $link
     * @param string $title
     * @param string $caption
     * @throws PrestaShopException
     */
    protected function _addSitemapNodeImage($fd, $link, $title, $caption)
    {
        fwrite($fd, '    <image:image>'.PHP_EOL);
        fwrite($fd, '      <image:loc>'.(Configuration::get('PS_REWRITING_SETTINGS') ? '<![CDATA['.$link.']]>' : $link).'</image:loc>'.PHP_EOL);
        if ($caption) {
            fwrite($fd, '      <image:caption><![CDATA['.$caption.']]></image:caption>'.PHP_EOL);
        }
        if ($title) {
            fwrite($fd, '      <image:title><![CDATA['.$title.']]></image:title>'.PHP_EOL);
        }
        fwrite($fd, '    </image:image>'.PHP_EOL);
    }

    /**
     * Store the generated Sitemap file to the database
     *
     * @param string $sitemap the name of the generated Google Sitemap file
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function _saveSitemapLink($sitemap)
    {
        if ($sitemap) {
            return Db::getInstance()->Execute('INSERT INTO `'._DB_PREFIX_.'sitemap_sitemap` (`link`, id_shop) VALUES (\''.pSQL($sitemap).'\', '.(int) $this->context->shop->id.')');
        }

        return false;
    }

    /**
     * Create the index file for all generated sitemaps
     *
     * @return boolean
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function _createIndexSitemap()
    {
        $sitemaps = Db::getInstance()->ExecuteS('SELECT `link` FROM `'._DB_PREFIX_.'sitemap_sitemap` WHERE id_shop = '.$this->context->shop->id);
        if (!$sitemaps) {
            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
        try {
            $xmlFeed = new SimpleXMLElement($xml);

            foreach ($sitemaps as $link) {
                $sitemap = $xmlFeed->addChild('sitemap');
                $sitemap->addChild('loc', 'http' . (Configuration::get('PS_SSL_ENABLED') ? 's' : '') . '://' . Tools::getShopDomain(false, true) . __PS_BASE_URI__ . $link['link']);
                $sitemap->addChild('lastmod', date('c'));
            }
            file_put_contents($this->normalizeDirectory(_PS_ROOT_DIR_) . $this->context->shop->id . '_index_sitemap.xml', $xmlFeed->asXML());
            return true;
        } catch (Exception $e) {
            Logger::addLog("sitemap: Failed to create index sitemap: " . $e);
            return false;
        }

    }

    /**
     * Hydrate $link_sitemap with home link
     *
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param array  $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function _getHomeLink(&$linkSitemap, $lang, &$index, &$i)
    {
       return $this->_addLinkToSitemap(
            $linkSitemap,
            [
                'type'  => 'home',
                'page'  => 'home',
                'link'  => $this->context->link->getPageLink('index')
            ],
            $lang['iso_code'],
            $index,
            $i,
            -1
        );
    }

    /**
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param array  $newLink     contain the link elements
     * @param string $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $id_obj      identifier of the object of the link to be added to the Gogle Sitemap file
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function _addLinkToSitemap(&$linkSitemap, $newLink, $lang, &$index, &$i, $id_obj)
    {
        if ($i <= 25000 && memory_get_usage() < 100000000) {
            $linkSitemap[] = $newLink;
            $i++;

            return true;
        } else {
            $this->_recursiveSitemapCreator($linkSitemap, $lang, $index);
            if ($index % 20 == 0 && !$this->cron) {
                $this->context->smarty->assign(
                    [
                        'sitemap_number'       => (int) $index,
                        'sitemap_refresh_page' => './index.php?tab=AdminModules&configure=sitemap&token='.Tools::getAdminTokenLite('AdminModules').'&tab_module='.$this->tab.'&module_name=sitemap&continue=1&type='.$newLink['type'].'&lang='.$lang.'&index='.$index.'&id='.intval($id_obj).'&id_shop='.$this->context->shop->id,
                    ]
                );

                return false;
            } else {
                if ($index % 20 == 0 && $this->cron) {
                    header('Refresh: 5; url=http'.(Configuration::get('PS_SSL_ENABLED') ? 's' : '').'://'.Tools::getShopDomain(false, true).__PS_BASE_URI__.'modules/sitemap/sitemap-cron.php?continue=1&token='.substr(Tools::encrypt('sitemap/cron'), 0, 10).'&type='.$newLink['type'].'&lang='.$lang.'&index='.$index.'&id='.intval($id_obj).'&id_shop='.$this->context->shop->id);
                    die();
                } else {
                    if ($this->cron) {
                        header('location: http'.(Configuration::get('PS_SSL_ENABLED') ? 's' : '').'://'.Tools::getShopDomain(false, true).__PS_BASE_URI__.'modules/sitemap/sitemap-cron.php?continue=1&token='.substr(Tools::encrypt('sitemap/cron'), 0, 10).'&type='.$newLink['type'].'&lang='.$lang.'&index='.$index.'&id='.intval($id_obj).'&id_shop='.$this->context->shop->id);
                    } else {
                        $adminFolder = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
                        $adminFolder = substr($adminFolder, 1);
                        header('location: http'.(Configuration::get('PS_SSL_ENABLED') ? 's' : '').'://'.Tools::getShopDomain(false, true).__PS_BASE_URI__.$adminFolder.'/index.php?tab=AdminModules&configure=sitemap&token='.Tools::getAdminTokenLite('AdminModules').'&tab_module='.$this->tab.'&module_name=sitemap&continue=1&type='.$newLink['type'].'&lang='.$lang.'&index='.$index.'&id='.intval($id_obj).'&id_shop='.$this->context->shop->id);
                    }
                    die();
                }
            }
        }
    }

    /**
     * Hydrate $link_sitemap with meta link
     *
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param array  $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idMeta      meta object identifier
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function _getMetaLink(&$linkSitemap, $lang, &$index, &$i, $idMeta = 0)
    {
        $link = $this->context->link;
        $query = (new DbQuery())
            ->select('id_meta, page')
            ->from('meta')
            ->where('configurable > 0')
            ->where('id_meta > ' . (int)$idMeta)
            ->orderBy('id_meta ASC');

        $disabledMetas = $this->getDisabledMetas();
        if ($disabledMetas) {
            $query->where('id_meta NOT IN (' . implode(',', $disabledMetas).')');
        }

        $metas = Db::getInstance()->ExecuteS($query);
        if (is_array($metas)) {
            foreach ($metas as $meta) {
                $page = $meta['page'];
                if (preg_match('#module-([a-z0-9_-]+)-([a-z0-9_]+)$#i', $page, $m)) {
                    $url = $link->getModuleLink($m[1], $m[2]);
                } else {
                    $url = $link->getPageLink($page);
                }

                if (!$this->_addLinkToSitemap(
                    $linkSitemap,
                    [
                        'type' => 'meta',
                        'page' => $page,
                        'link' => $url
                    ],
                    $lang['iso_code'],
                    $index,
                    $i,
                    $meta['id_meta']
                )) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Hydrate $link_sitemap with products link
     *
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param array  $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idProduct   product object identifier
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function _getProductLink(&$linkSitemap, $lang, &$index, &$i, $idProduct = 0)
    {
        $link = $this->context->link;
        $idProducts = Db::getInstance()->ExecuteS('SELECT `id_product` FROM `'._DB_PREFIX_.'product_shop` WHERE `id_product` >= '.intval($idProduct).' AND `active` = 1 AND `visibility` != \'none\' AND `id_shop`='.$this->context->shop->id.' ORDER BY `id_product` ASC');

        foreach ($idProducts as $idProduct) {
            $product = new Product((int) $idProduct['id_product'], false, (int) $lang['id_lang']);

            $url = $link->getProductLink($product, $product->link_rewrite, $product->category, $product->ean13, (int) $lang['id_lang'], (int) $this->context->shop->id, 0, true);

            $images = [];
            $productImages = Image::getImages($lang['id_lang'], $idProduct['id_product']);
            if (is_array($productImages)) {
                foreach ($productImages as $productImage) {
                    $id = (int)$productImage['id_image'];
                    if ($this->imageExists(_PS_PROD_IMG_DIR_ . Image::getImgFolderStatic($id), $id)) {
                        $imageLink = $link->getImageLink($product->link_rewrite, $id, $this->getImageType('products'));
                        if ($imageLink) {
                            $title = $productImage['legend'];
                            if (! $title) {
                                $title = $product->name;
                            }
                            $images[] = [
                                'title_img' => $title,
                                'caption' => $product->description_short,
                                'link' => $imageLink,
                            ];
                        }
                    }
                }
            }

            if (!$this->_addLinkToSitemap(
                $linkSitemap,
                [
                    'type'    => 'product',
                    'page'    => 'product',
                    'lastmod' => $product->date_upd,
                    'link'    => $url,
                    'images'  => $images,
                ],
                $lang['iso_code'],
                $index,
                $i,
                $idProduct['id_product']
            )
            ) {
                return false;
            }

            unset($imageLink);
        }

        return true;
    }

    /**
     * Hydrate $link_sitemap with categories link
     *
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param array  $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idCategory  category object identifier
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function _getCategoryLink(&$linkSitemap, $lang, &$index, &$i, $idCategory = 0)
    {
        $link = $this->context->link;
        $rootCategoryId = (int) Configuration::get('PS_ROOT_CATEGORY');
        $homeCategoryId = (int) Configuration::get('PS_HOME_CATEGORY');
        $categoryIds = Db::getInstance()->ExecuteS(
            'SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
                INNER JOIN `'._DB_PREFIX_.'category_shop` cs ON c.`id_category` = cs.`id_category`
                WHERE c.`id_category` >= '.(int) $idCategory.' AND c.`active` = 1 AND c.`id_category` != '.$rootCategoryId.' AND c.`id_category` != '.$homeCategoryId.' AND c.id_parent > 0 AND c.`id_category` > 0 AND cs.`id_shop` = '.(int) $this->context->shop->id.' AND c.`is_root_category` != 1 ORDER BY c.`id_category` ASC'
        );

        foreach ($categoryIds as $categoryId) {
            $id = (int) $categoryId['id_category'];
            $category = new Category($id, (int) $lang['id_lang']);
            $url = $link->getCategoryLink($category, $category->link_rewrite, (int) $lang['id_lang']);

            $imageLink = $this->imageExists(_PS_CAT_IMG_DIR_, $id)
                ? $this->getImageLink('categories', $id, $category->link_rewrite)
                : null;

            $imageCategory = [];
            if ($imageLink) {
                $imageCategory = [
                    'title_img' => $category->name,
                    'link'      => $imageLink,
                ];
            }

            if (!$this->_addLinkToSitemap(
                $linkSitemap,
                [
                    'type'    => 'category',
                    'page'    => 'category',
                    'lastmod' => $category->date_upd,
                    'link'    => $url,
                    'image'   => $imageCategory,
                ],
                $lang['iso_code'],
                $index,
                $i,
                (int) $categoryId['id_category']
            )) {
                return false;
            }

            unset($imageLink);
        }

        return true;
    }

    /**
     * return the link elements for the manufacturer object
     *
     * @param array  $linkSitemap    contain all the links for the Google Sitemap file to be generated
     * @param array  $lang           language of link to add
     * @param int    $index          index of the current Google Sitemap file
     * @param int    $i              count of elements added to sitemap main array
     * @param int    $idManufacturer manufacturer object identifier
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function _getManufacturerLink(&$linkSitemap, $lang, &$index, &$i, $idManufacturer = 0)
    {
        $link = $this->context->link;
        $manufacturersId = $this->getIds(
            'SELECT m.`id_manufacturer` AS `id`
            FROM `'._DB_PREFIX_.'manufacturer` m
            INNER JOIN `'._DB_PREFIX_.'manufacturer_lang` ml on m.`id_manufacturer` = ml.`id_manufacturer`
            INNER JOIN `'._DB_PREFIX_.'manufacturer_shop` ms ON m.`id_manufacturer` = ms.`id_manufacturer`
            WHERE m.`active` = 1
            AND m.`id_manufacturer` >= '.(int) $idManufacturer.'
            AND ms.`id_shop` = '.(int) $this->context->shop->id . '
            AND ml.`id_lang` = '.(int) $lang['id_lang'].'
            ORDER BY m.`id_manufacturer` ASC'
        );

        foreach ($manufacturersId as $id) {

            // Check if manufacturer has any active product
            $query = new DbQuery();
            $query->select('COUNT(*)');
            $query->from('product', 'p');
            $query->innerJoin('product_shop', 'ps', 'p.id_product=ps.id_product AND ps.id_shop='. Context::getContext()->shop->id);
            $query->where('p.id_manufacturer = ' . $id);
            $query->where('ps.active = 1');

            if (! Db::getInstance()->getValue($query)) {
                continue;
            }

            $manufacturer = new Manufacturer($id, $lang['id_lang']);
            $url = $link->getManufacturerLink($manufacturer, $manufacturer->link_rewrite, $lang['id_lang']);

            $imageLink = $this->imageExists(_PS_MANU_IMG_DIR_, $id)
                ? $this->getImageLink('manufacturers', $id)
                : null;

            $manufacturerImage = [];
            if ($imageLink) {
                $manufacturerImage = [
                    'title_img' => $manufacturer->name,
                    'caption'   => $manufacturer->short_description,
                    'link'      => $imageLink,
                ];
            }
            if (!$this->_addLinkToSitemap(
                $linkSitemap,
                [
                    'type'    => 'manufacturer',
                    'page'    => 'manufacturer',
                    'lastmod' => $manufacturer->date_upd,
                    'link'    => $url,
                    'image'   => $manufacturerImage,
                ],
                $lang['iso_code'],
                $index,
                $i,
                $id
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param array  $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idSupplier  supplier object identifier
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function _getSupplierLink(&$linkSitemap, $lang, &$index, &$i, $idSupplier = 0)
    {
        $link = $this->context->link;
        $suppliersId = $this->getIds(
            'SELECT s.`id_supplier` as `id`
            FROM `'._DB_PREFIX_.'supplier` s
            INNER JOIN `'._DB_PREFIX_.'supplier_lang` sl ON s.`id_supplier` = sl.`id_supplier`
            INNER JOIN `'._DB_PREFIX_.'supplier_shop` ss ON s.`id_supplier` = ss.`id_supplier`
            WHERE s.`active` = 1 
            AND s.`id_supplier` >= '.(int) $idSupplier.'
            AND ss.`id_shop` = '.(int) $this->context->shop->id.'
            AND sl.`id_lang` = '.(int) $lang['id_lang'].'
            ORDER BY s.`id_supplier` ASC'
        );
        foreach ($suppliersId as $id) {
            $supplier = new Supplier($id, $lang['id_lang']);
            $url = $link->getSupplierLink($supplier, $supplier->link_rewrite, $lang['id_lang']);

            $imageLink = $this->imageExists(_PS_SUPP_IMG_DIR_, $id)
                ? $this->getImageLink('suppliers', $id)
                : null;

            $supplierImage = [];
            if ($imageLink) {
                $supplierImage = [
                    'title_img' => $supplier->name,
                    'link'      => $imageLink
                ];
            }
            if (!$this->_addLinkToSitemap(
                $linkSitemap,
                [
                    'type'    => 'supplier',
                    'page'    => 'supplier',
                    'lastmod' => $supplier->date_upd,
                    'link'    => $url,
                    'image'   => $supplierImage,
                ],
                $lang['iso_code'],
                $index,
                $i,
                $id
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * return the link elements for the CMS object
     *
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param array  $lang        the language of link to add
     * @param int    $index       the index of the current Google Sitemap file
     * @param int    $i           the count of elements added to sitemap main array
     * @param int    $idCms       the CMS object identifier
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function _getCmsLink(&$linkSitemap, $lang, &$index, &$i, $idCms = 0)
    {
        $link = $this->context->link;
        $cmssId = $this->getIds(
            'SELECT c.`id_cms` as `id`
            FROM `'._DB_PREFIX_.'cms` c
            INNER JOIN `'._DB_PREFIX_.'cms_shop` cs ON (c.`id_cms` = cs.`id_cms`)
            INNER JOIN `'._DB_PREFIX_.'cms_lang` cl ON (c.`id_cms` = cl.`id_cms` AND cl.id_shop = cs.id_shop)
            INNER JOIN `'._DB_PREFIX_.'cms_category` cc ON (c.id_cms_category = cc.id_cms_category AND cc.active = 1)
            WHERE c.`active` = 1
            AND c.`indexation` = 1 
            AND c.`id_cms` >= '.(int) $idCms.'
            AND cs.id_shop = '.(int) $this->context->shop->id.'
            AND cl.`id_lang` = '.(int) $lang['id_lang'].'
            ORDER BY c.`id_cms` ASC'
        );

        foreach ($cmssId as $id) {
            $cms = new CMS($id, $lang['id_lang']);
            $cms->link_rewrite = (is_array($cms->link_rewrite) ? $cms->link_rewrite[(int) $lang['id_lang']] : $cms->link_rewrite);
            $url = $link->getCMSLink($cms, null, null, $lang['id_lang']);

            if (!$this->_addLinkToSitemap(
                $linkSitemap,
                [
                    'type'  => 'cms',
                    'page'  => 'cms',
                    'link'  => $url,
                    'image' => false,
                ],
                $lang['iso_code'],
                $index,
                $i,
                $id
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns link elements generated by modules subscribes to hook sitemap::HOOK_ADD_URLS
     *
     * The hook expects modules to return a vector of associative arrays each of them being acceptable by
     *   the sitemap::_addLinkToSitemap() second attribute (minus the 'type' index).
     * The 'type' index is automatically set to 'module' (not sure here, should we be safe or trust modules?).
     *
     * @param array  $linkSitemap by ref. accumulator for all the links for the Google Sitemap file to be generated
     * @param array  $lang        the language being processed
     * @param int    $index       the index of the current Google Sitemap file
     * @param int    $i           the count of elements added to sitemap main array
     * @param int    $numLink     restart at link number #$num_link
     *
     * @return boolean
     * @throws PrestaShopException
     */
    protected function _getModuleLink(&$linkSitemap, $lang, &$index, &$i, $numLink = 0)
    {
        $modulesLinks = Hook::exec(self::HOOK_ADD_URLS, ['lang' => $lang], null, true);
        if (empty($modulesLinks) || !is_array($modulesLinks)) {
            return true;
        }
        $links = [];
        foreach ($modulesLinks as $moduleName => $moduleLinks) {
            foreach ($moduleLinks as &$moduleLink) {
                $moduleLink['type'] = 'module';
                $moduleLink['moduleName'] = $moduleName;
            }
            $links = array_merge($links, $moduleLinks);
        }
        foreach ($links as $n => $link) {
            if ($numLink > $n) {
                continue;
            }
            if (!$this->_addLinkToSitemap($linkSitemap, $link, $lang['iso_code'], $index, $i, $n)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $class
     * @param int $id
     * @param string $rewrite
     * @return string
     * @throws PrestaShopException
     */
    protected function getImageLink($class, $id, $rewrite='')
    {
        return Link::getGenericImageLink(
            $class,
            $id,
            $this->getImageType($class),
            '',
            null,
            $rewrite
        );
    }

    /**
     * Returns image type for given $class
     *
     * @param $class
     * @return string
     * @throws PrestaShopException
     */
    protected function getImageType($class)
    {
        $types = $this->getSelectedImageTypes();
        if (! array_key_exists($class, $types)) {
            throw new RuntimeException('Invalid class: ' . $class);
        }
        return $types[$class];
    }

    /**
     * Returns selected image types for each image class
     *
     * @return array
     * @throws PrestaShopException
     */
    protected function getSelectedImageTypes()
    {
        $selectedTypes = [];
        foreach ($this->getAllImageTypes() as $class => $types) {
            $selected = Configuration::get('SITEMAP_IMAGE_TYPE_' . strtoupper($class));
            if ($selected && in_array($selected, $types)) {
                $selectedTypes[$class] = $selected;
            } else {
                if (count($types) > 0) {
                    $selectedTypes[$class] = $types[0];
                } else {
                    $selectedTypes[$class] = '';
                }
            }
        }
        return $selectedTypes;
    }

    /**
     * Return image types indexed by class
     *
     * @throws PrestaShopException
     * @return array
     */
    protected function getAllImageTypes()
    {
        $types = [
            'products' => [],
            'categories' => [],
            'suppliers' => [],
            'manufacturers' => []
        ];
        foreach (ImageType::getImagesTypes(null, true) as $typeDefinition) {
            if ($typeDefinition['products']) {
                $types['products'][] = $typeDefinition['name'];
            }
            if ($typeDefinition['categories']) {
                $types['categories'][] = $typeDefinition['name'];
            }
            if ($typeDefinition['suppliers']) {
                $types['suppliers'][] = $typeDefinition['name'];
            }
            if ($typeDefinition['manufacturers']) {
                $types['manufacturers'][] = $typeDefinition['name'];
            }
        }
        return $types;
    }

    /**
     * @param string $sql
     * @param string $idColumn
     * @return int[]
     * @throws PrestaShopException
     */
    protected function getIds($sql, $idColumn='id')
    {
        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (is_array($results)) {
            return array_map('intval', array_column($results, $idColumn));
        }
        return [];
    }

    /**
     * Returns link types that are allowed for generation
     *
     * @return array
     * @throws PrestaShopException
     */
    protected function getAllowedLinkTypes()
    {
        static $typeArray = null;
        if (is_null($typeArray)) {
            $typeArray = ['home', 'meta', 'product', 'category', 'manufacturer', 'supplier', 'cms', 'module'];

            $metas = Meta::getMetas();
            $disabledMetas = $this->getDisabledMetas();
            foreach ($metas as $meta) {
                if (in_array($meta['id_meta'], $disabledMetas)) {
                    if (($key = array_search($meta['page'], $typeArray)) !== false) {
                        unset($typeArray[$key]);
                    }
                }
            }
        }
        return $typeArray;
    }

    /**
     * @return int[]
     * @throws PrestaShopException
     */
    protected function getDisabledMetas()
    {
        $disabledLink = Configuration::get('SITEMAP_DISABLE_LINKS');
        if ($disabledLink) {
            return array_filter(array_map('intval', explode(',', $disabledLink)));
        }
        return [];
    }

    /**
     * @param array $link
     * @return string
     */
    protected function resolvePage($link)
    {
        if (isset($link['page'])) {
            return $link['page'];
        }
        if (isset($link['moduleName'])) {
            return $link['moduleName'];
        }
        return 'unknown-page';
    }

    /**
     * @param int[] $shopIds
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function sitemapsGenerated($shopIds)
    {
        $context = Context::getContext();
        /** @var AdminController $controller */
        $controller = $context->controller;
        $link = $context->link;

        $cnt = count($shopIds);
        $controller->confirmations[] = ($cnt > 1)
            ? sprintf($this->l('Sitemaps for %s stores have been generated'), $cnt)
            : sprintf($this->l('Sitemap for %s has been generated'), $context->shop->name);

        $controller->setRedirectAfter($link->getAdminLink('AdminModules', true, [
            'configure' => $this->name,
            'module_name' => $this->name
        ]));
    }

    /**
     * @param string $directory
     * @param string $name
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    protected function imageExists($directory, $name)
    {
        if (method_exists(ImageManager::class, 'getSourceImage')) {
            return (bool)ImageManager::getSourceImage($directory, $name);
        }
        // legacy check functionality
        return file_exists(ltrim($directory, '/') . '/' . $name . '.jpg');
    }

}
