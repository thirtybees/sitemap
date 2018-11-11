<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
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
    const HOOK_ADD_URLS = 'gSitemapAppendUrls';

    public $cron = false;
    protected $sql_checks = [];

    /**
     * Gsitemap constructor.
     */
    public function __construct()
    {
        $this->name = 'sitemap';
        $this->tab = 'seo';
        $this->version = '4.0.2';
        $this->author = 'thirty bees';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Sitemap');
        $this->description = $this->l('Generate your sitemap file');

        $this->type_array = ['home', 'meta', 'product', 'category', 'manufacturer', 'supplier', 'cms', 'module'];

        $metas = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'meta` ORDER BY `id_meta` ASC');
        $disabledMetas = explode(',', Configuration::get('SITEMAP_DISABLE_LINKS'));
        foreach ($metas as $meta) {
            if (in_array($meta['id_meta'], $disabledMetas)) {
                if (($key = array_search($meta['page'], $this->type_array)) !== false) {
                    unset($this->type_array[$key]);
                }
            }
        }

    }

    /**
     * Google Sitemap installation process:
     *
     * Step 1 - Pre-set Configuration option values
     * Step 2 - Install the Addon and create a database table to store Sitemap files name by shop
     *
     * @return boolean Installation result
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
                     'SITEMAP_CHECK_IMAGE_FILE'      => false,
                     'SITEMAP_LAST_EXPORT'           => false,
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
     */
    protected function _installHook()
    {
        $hook = new Hook();
        $hook->name = self::HOOK_ADD_URLS;
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
     */
    public function uninstall()
    {
        foreach ([
                     'SITEMAP_PRIORITY_HOME'         => '',
                     'SITEMAP_PRIORITY_PRODUCT'      => '',
                     'SITEMAP_PRIORITY_CATEGORY'     => '',
                     'SITEMAP_PRIORITY_MANUFACTURER' => '',
                     'SITEMAP_PRIORITY_SUPPLIER'     => '',
                     'SITEMAP_PRIORITY_CMS'          => '',
                     'SITEMAP_FREQUENCY'             => '',
                     'SITEMAP_CHECK_IMAGE_FILE'      => '',
                     'SITEMAP_LAST_EXPORT'           => '',
                 ] as $key => $val) {
            if (!Configuration::deleteByName($key)) {
                return false;
            }
        }

        $hook = new Hook(Hook::getIdByName(self::HOOK_ADD_URLS));
        if (Validate::isLoadedObject($hook)) {
            $hook->delete();
        }

        return parent::uninstall() && $this->removeSitemap();
    }

    /**
     * Delete all the generated Sitemap files  and drop the addon table.
     *
     * @return boolean
     */
    public function removeSitemap()
    {
        try {
            $links = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT * FROM `'._DB_PREFIX_.'sitemap_sitemap`');
            if ($links) {
                foreach ($links as $link) {
                    if (!@unlink($this->normalizeDirectory(_PS_ROOT_DIR_).$link['link'])) {
                        return false;
                    }
                }
            }
        } catch (Exception $e) {
        }

        if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'sitemap_sitemap`')) {
            return false;
        }

        return true;
    }

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

    public function getContent()
    {
        /* Store the posted parameters and generate a new Google Sitemap files for the current Shop */
        if (Tools::isSubmit('SubmitGsitemap')) {
            Configuration::updateValue('SITEMAP_FREQUENCY', pSQL(Tools::getValue('sitemap_frequency')));
            Configuration::updateValue('SITEMAP_INDEX_CHECK', '');
            Configuration::updateValue('SITEMAP_CHECK_IMAGE_FILE', pSQL(Tools::getValue('sitemap_check_image_file')));
            $meta = '';
            if (Tools::getValue('sitemap_meta')) {
                $meta .= implode(', ', Tools::getValue('sitemap_meta'));
            }
            Configuration::updateValue('SITEMAP_DISABLE_LINKS', $meta);
            $this->emptySitemap();
            $this->createSitemap();
        } /* if no posted form and the variable [continue] is found in the HTTP request variable keep creating sitemap */
        elseif (Tools::getValue('continue')) {
            $this->createSitemap();
        }

        /* Empty the Shop domain cache */
        if (method_exists('ShopUrl', 'resetMainDomainCache')) {
            ShopUrl::resetMainDomainCache();
        }

        $this->context->smarty->assign(
            [
                'sitemap_form'             => './index.php?tab=AdminModules&configure=sitemap&token='.Tools::getAdminTokenLite('AdminModules').'&tab_module='.$this->tab.'&module_name=sitemap',
                'sitemap_cron'             => _PS_BASE_URL_._MODULE_DIR_.'sitemap/sitemap-cron.php?token='.substr(Tools::encrypt('sitemap/cron'), 0, 10).'&id_shop='.$this->context->shop->id,
                'sitemap_feed_exists'      => file_exists($this->normalizeDirectory(_PS_ROOT_DIR_).'index_sitemap.xml'),
                'sitemap_last_export'      => Configuration::get('SITEMAP_LAST_EXPORT'),
                'sitemap_frequency'        => Configuration::get('SITEMAP_FREQUENCY'),
                'sitemap_store_url'        => 'http://'.Tools::getShopDomain(false, true).__PS_BASE_URI__,
                'sitemap_links'            => Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'sitemap_sitemap` WHERE id_shop = '.(int) $this->context->shop->id),
                'store_metas'               => Meta::getMetasByIdLang((int) $this->context->cookie->id_lang),
                'sitemap_disable_metas'    => explode(',', Configuration::get('SITEMAP_DISABLE_LINKS')),
                'sitemap_customer_limit'   => [
                    'max_exec_time' => (int) ini_get('max_execution_time'),
                    'memory_limit'  => intval(ini_get('memory_limit')),
                ],
                'prestashop_ssl'            => Configuration::get('PS_SSL_ENABLED'),
                'sitemap_check_image_file' => Configuration::get('SITEMAP_CHECK_IMAGE_FILE'),
                'shop'                      => $this->context->shop,
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
     */
    public function emptySitemap($idShop = 0)
    {
        if (!isset($this->context)) {
            $this->context = new Context();
        }
        if ($idShop != 0) {
            $this->context->shop = new Shop((int) $idShop);
        }
        $links = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'sitemap_sitemap` WHERE id_shop = '.(int) $this->context->shop->id);
        if ($links) {
            foreach ($links as $link) {
                @unlink($this->normalizeDirectory(_PS_ROOT_DIR_).$link['link']);
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
     */
    public function createSitemap($idShop = 0)
    {
        if (@fopen($this->normalizeDirectory(_PS_ROOT_DIR_).'/test.txt', 'w') == false) {
            $this->context->smarty->assign('google_maps_error', $this->l('An error occured while trying to check your file permissions. Please adjust your permissions to allow PrestaShop to write a file in your root directory.'));

            return false;
        } else {
            @unlink($this->normalizeDirectory(_PS_ROOT_DIR_).'test.txt');
        }

        if ($idShop != 0) {
            $this->context->shop = new Shop((int) $idShop);
        }

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

            $linkSitemap = [];
            foreach ($this->type_array as $typeVal) {
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
            $page = '';
            $index = 0;
        }

        $this->_createIndexSitemap();
        Configuration::updateValue('SITEMAP_LAST_EXPORT', date('r'));
        Tools::file_get_contents('http://www.google.com/webmasters/sitemaps/ping?sitemap='.urlencode('http'.(Configuration::get('PS_SSL_ENABLED') ? 's' : '').'://'.Tools::getShopDomain(false, true).$this->context->shop->physical_uri.$this->context->shop->virtual_uri.$this->context->shop->id.'_index_sitemap.xml'));

        if ($this->cron) {
            die();
        }
        header('location: ./index.php?tab=AdminModules&configure=sitemap&token='.Tools::getAdminTokenLite('AdminModules').'&tab_module='.$this->tab.'&module_name=sitemap&validation');
        die();
    }

    /**
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param string $lang        the language of link to add
     * @param int    $index       the index of the current Google Sitemap file
     *
     * @return bool
     */
    protected function _recursiveSitemapCreator($linkSitemap, $lang, &$index)
    {
        if (!count($linkSitemap)) {
            return false;
        }

        $sitemapLink = $this->context->shop->id.'_'.$lang.'_'.$index.'_sitemap.xml';
        $writeFd = fopen($this->normalizeDirectory(_PS_ROOT_DIR_).$sitemapLink, 'w');

        fwrite($writeFd, '<?xml version="1.0" encoding="UTF-8"?>'."\r\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\r\n");
        foreach ($linkSitemap as $key => $file) {
            fwrite($writeFd, '<url>'."\r\n");
            $lastmod = (isset($file['lastmod']) && !empty($file['lastmod'])) ? date('c', strtotime($file['lastmod'])) : null;
            $this->_addSitemapNode($writeFd, htmlspecialchars(strip_tags($file['link'])), $this->_getPriorityPage($file['page']), Configuration::get('SITEMAP_FREQUENCY'), $lastmod);
            if ($file['image']) {
                $this->_addSitemapNodeImage(
                    $writeFd, htmlspecialchars(strip_tags($file['image']['link'])), isset($file['image']['title_img']) ? htmlspecialchars(
                    str_replace(
                        [
                            "\r\n",
                            "\r",
                            "\n",
                        ], '', strip_tags($file['image']['title_img'])
                    )
                ) : '', isset($file['image']['caption']) ? htmlspecialchars(
                    str_replace(
                        [
                            "\r\n",
                            "\r",
                            "\n",
                        ], '', strip_tags($file['image']['caption'])
                    )
                ) : ''
                );
            }
            fwrite($writeFd, '</url>'."\r\n");
        }
        fwrite($writeFd, '</urlset>'."\r\n");
        fclose($writeFd);
        $this->_saveSitemapLink($sitemapLink);
        $index++;

        return true;
    }

    /**
     * Add a new line to the sitemap file
     *
     * @param resource $fd       file system object resource
     * @param string   $loc      string the URL of the object page
     * @param string   $priority
     * @param string   $change_freq
     * @param int      $last_mod the last modification date/time as a timestamp
     */
    protected function _addSitemapNode($fd, $loc, $priority, $change_freq, $last_mod = null)
    {
        fwrite($fd, '<loc>'.(Configuration::get('PS_REWRITING_SETTINGS') ? '<![CDATA['.$loc.']]>' : $loc).'</loc>'."\r\n".'<priority>'.number_format($priority, 1, '.', '').'</priority>'."\r\n".($last_mod ? '<lastmod>'.date('c', strtotime($last_mod)).'</lastmod>' : '')."\r\n".'<changefreq>'.$change_freq.'</changefreq>'."\r\n");
    }

    /**
     * return the priority value set in the configuration parameters
     *
     * @param string $page
     *
     * @return float|string|bool
     */
    protected function _getPriorityPage($page)
    {
        return Configuration::get('SITEMAP_PRIORITY_'.Tools::strtoupper($page)) ? Configuration::get('SITEMAP_PRIORITY_'.Tools::strtoupper($page)) : 0.1;
    }

    protected function _addSitemapNodeImage($fd, $link, $title, $caption)
    {
        fwrite($fd, '<image:image>'."\r\n".'<image:loc>'.(Configuration::get('PS_REWRITING_SETTINGS') ? '<![CDATA['.$link.']]>' : $link).'</image:loc>'."\r\n".'<image:caption><![CDATA['.$caption.']]></image:caption>'."\r\n".'<image:title><![CDATA['.$title.']]></image:title>'."\r\n".'</image:image>'."\r\n");
    }

    /**
     * Store the generated Sitemap file to the database
     *
     * @param string $sitemap the name of the generated Google Sitemap file
     *
     * @return bool
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
     */
    protected function _createIndexSitemap()
    {
        $sitemaps = Db::getInstance()->ExecuteS('SELECT `link` FROM `'._DB_PREFIX_.'sitemap_sitemap` WHERE id_shop = '.$this->context->shop->id);
        if (!$sitemaps) {
            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
        $xmlFeed = new SimpleXMLElement($xml);

        foreach ($sitemaps as $link) {
            $sitemap = $xmlFeed->addChild('sitemap');
            $sitemap->addChild('loc', 'http'.(Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE') ? 's' : '').'://'.Tools::getShopDomain(false, true).__PS_BASE_URI__.$link['link']);
            $sitemap->addChild('lastmod', date('c'));
        }
        file_put_contents($this->normalizeDirectory(_PS_ROOT_DIR_).$this->context->shop->id.'_index_sitemap.xml', $xmlFeed->asXML());

        return true;
    }

    /**
     * Hydrate $link_sitemap with home link
     *
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param string $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     *
     * @return bool
     */
    protected function _getHomeLink(&$linkSitemap, $lang, &$index, &$i)
    {
        if (Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE')) {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        return $this->_addLinkToSitemap(
            $linkSitemap,
            [
                'type'  => 'home',
                'page'  => 'home',
                'link'  => $protocol.Tools::getShopDomainSsl(false).$this->context->shop->getBaseURI().(method_exists('Language', 'isMultiLanguageActivated') ? Language::isMultiLanguageActivated() ? $lang['iso_code'].'/' : '' : ''),
                'image' => false,
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
     * @param string $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idMeta      meta object identifier
     *
     * @return bool
     */
    protected function _getMetaLink(&$linkSitemap, $lang, &$index, &$i, $idMeta = 0)
    {
        if (method_exists('ShopUrl', 'resetMainDomainCache')) {
            ShopUrl::resetMainDomainCache();
        }
        $link = new Link();
        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $metas = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'meta` WHERE `configurable` > 0 AND `id_meta` >= '.(int) $idMeta.' ORDER BY `id_meta` ASC');
        } else {
            $metas = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'meta` WHERE `id_meta` >= '.(int) $idMeta.' ORDER BY `id_meta` ASC');
        }
        foreach ($metas as $meta) {
            $url = '';
            if (!in_array($meta['id_meta'], explode(',', Configuration::get('SITEMAP_DISABLE_LINKS')))) {
                $urlRewrite = Db::getInstance()->getValue('SELECT url_rewrite, id_shop FROM `'._DB_PREFIX_.'meta_lang` WHERE `id_meta` = '.(int) $meta['id_meta'].' AND `id_shop` ='.(int) $this->context->shop->id.' AND `id_lang` = '.(int) $lang['id_lang']);
                Dispatcher::getInstance()->addRoute($meta['page'], (isset($urlRewrite) ? $urlRewrite : $meta['page']), $meta['page'], $lang['id_lang']);
                $uriPath = Dispatcher::getInstance()->createUrl($meta['page'], $lang['id_lang'], [], (bool) Configuration::get('PS_REWRITING_SETTINGS'));
                $url .= Tools::getShopDomainSsl(true).(($this->context->shop->virtual_uri) ? __PS_BASE_URI__.$this->context->shop->virtual_uri : __PS_BASE_URI__).(Language::isMultiLanguageActivated() ? $lang['iso_code'].'/' : '').ltrim($uriPath, '/');

                if (!$this->_addLinkToSitemap(
                    $linkSitemap,
                    [
                        'type'  => 'meta',
                        'page'  => $meta['page'],
                        'link'  => $url,
                        'image' => false,
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
     * @param string $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idProduct   product object identifier
     *
     * @return bool
     */
    protected function _getProductLink(&$linkSitemap, $lang, &$index, &$i, $idProduct = 0)
    {
        $link = new Link();
        if (method_exists('ShopUrl', 'resetMainDomainCache')) {
            ShopUrl::resetMainDomainCache();
        }

        $idProducts = Db::getInstance()->ExecuteS('SELECT `id_product` FROM `'._DB_PREFIX_.'product_shop` WHERE `id_product` >= '.intval($idProduct).' AND `active` = 1 AND `visibility` != \'none\' AND `id_shop`='.$this->context->shop->id.' ORDER BY `id_product` ASC');

        foreach ($idProducts as $idProduct) {
            $product = new Product((int) $idProduct['id_product'], false, (int) $lang['id_lang']);

            $url = $link->getProductLink($product, $product->link_rewrite, htmlspecialchars(strip_tags($product->category)), $product->ean13, (int) $lang['id_lang'], (int) $this->context->shop->id, 0, true);

            $idImage = Product::getCover((int) $idProduct['id_product']);
            if (isset($idImage['id_image'])) {
                $imageLink = $this->context->link->getImageLink($product->link_rewrite, $product->id.'-'.(int) $idImage['id_image'], 'large_default');
                $imageLink = (!in_array(rtrim(Context::getContext()->shop->virtual_uri, '/'), explode('/', $imageLink))) ? str_replace(
                    [
                        'https',
                        Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri,
                    ], [
                    'http',
                    Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri.Context::getContext()->shop->virtual_uri,
                ], $imageLink
                ) : $imageLink;
            }
            $fileHeaders = (Configuration::get('SITEMAP_CHECK_IMAGE_FILE')) ? @get_headers($imageLink) : true;
            $imageProduct = [];
            if (isset($imageLink) && ($fileHeaders[0] != 'HTTP/1.1 404 Not Found' || $fileHeaders === true)) {
                $imageProduct = [
                    'title_img' => htmlspecialchars(strip_tags($product->name)),
                    'caption'   => htmlspecialchars(strip_tags($product->description_short)),
                    'link'      => $imageLink,
                ];
            }
            if (!$this->_addLinkToSitemap(
                $linkSitemap,
                [
                    'type'    => 'product',
                    'page'    => 'product',
                    'lastmod' => $product->date_upd,
                    'link'    => $url,
                    'image'   => $imageProduct,
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
     * @param string $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idCategory  category object identifier
     *
     * @return bool
     */
    protected function _getCategoryLink(&$linkSitemap, $lang, &$index, &$i, $idCategory = 0)
    {
        $link = new Link();
        if (method_exists('ShopUrl', 'resetMainDomainCache')) {
            ShopUrl::resetMainDomainCache();
        }

        $rootCategoryId = (int) Configuration::get('PS_ROOT_CATEGORY');
        $homeCategoryId = (int) Configuration::get('PS_HOME_CATEGORY');
        $categoryIds = Db::getInstance()->ExecuteS(
            'SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
				INNER JOIN `'._DB_PREFIX_.'category_shop` cs ON c.`id_category` = cs.`id_category`
				WHERE c.`id_category` >= '.(int) $idCategory.' AND c.`active` = 1 AND c.`id_category` != '.$rootCategoryId.' AND c.`id_category` != '.$homeCategoryId.' AND c.id_parent > 0 AND c.`id_category` > 0 AND cs.`id_shop` = '.(int) $this->context->shop->id.' AND c.`is_root_category` != 1 ORDER BY c.`id_category` ASC'
        );

        foreach ($categoryIds as $categoryId) {
            $category = new Category((int) $categoryId['id_category'], (int) $lang['id_lang']);
            $url = $link->getCategoryLink($category, urlencode($category->link_rewrite), (int) $lang['id_lang']);

            if ($category->id_image) {
                $imageLink = $this->context->link->getCatImageLink($category->link_rewrite, (int) $category->id_image, 'category_default');
                $imageLink = (!in_array(rtrim(Context::getContext()->shop->virtual_uri, '/'), explode('/', $imageLink))) ? str_replace(
                    [
                        'https',
                        Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri,
                    ], [
                    'http',
                    Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri.Context::getContext()->shop->virtual_uri,
                ], $imageLink
                ) : $imageLink;
            }
            $fileHeaders = (Configuration::get('SITEMAP_CHECK_IMAGE_FILE')) ? @get_headers($imageLink) : true;
            $imageCategory = [];
            if (isset($imageLink) && ($fileHeaders[0] != 'HTTP/1.1 404 Not Found' || $fileHeaders === true)) {
                $imageCategory = [
                    'title_img' => htmlspecialchars(strip_tags($category->name)),
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
     * @param string $lang           language of link to add
     * @param int    $index          index of the current Google Sitemap file
     * @param int    $i              count of elements added to sitemap main array
     * @param int    $idManufacturer manufacturer object identifier
     *
     * @return bool
     */
    protected function _getManufacturerLink(&$linkSitemap, $lang, &$index, &$i, $idManufacturer = 0)
    {
        $link = new Link();
        if (method_exists('ShopUrl', 'resetMainDomainCache')) {
            ShopUrl::resetMainDomainCache();
        }
        $manufacturersId = Db::getInstance()->ExecuteS(
            'SELECT m.`id_manufacturer` FROM `'._DB_PREFIX_.'manufacturer` m
			INNER JOIN `'._DB_PREFIX_.'manufacturer_lang` ml on m.`id_manufacturer` = ml.`id_manufacturer`'.
            ($this->tableColumnExists(_DB_PREFIX_.'manufacturer_shop') ? ' INNER JOIN `'._DB_PREFIX_.'manufacturer_shop` ms ON m.`id_manufacturer` = ms.`id_manufacturer` ' : '').
            ' WHERE m.`active` = 1  AND m.`id_manufacturer` >= '.(int) $idManufacturer.
            ($this->tableColumnExists(_DB_PREFIX_.'manufacturer_shop') ? ' AND ms.`id_shop` = '.(int) $this->context->shop->id : '').
            ' AND ml.`id_lang` = '.(int) $lang['id_lang'].
            ' ORDER BY m.`id_manufacturer` ASC'
        );
        foreach ($manufacturersId as $manufacturerId) {
            $manufacturer = new Manufacturer((int) $manufacturerId['id_manufacturer'], $lang['id_lang']);
            $url = $link->getManufacturerLink($manufacturer, $manufacturer->link_rewrite, $lang['id_lang']);

            $imageLink = 'http'.(Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE') ? 's' : '').'://'.Tools::getMediaServer(_THEME_MANU_DIR_)._THEME_MANU_DIR_.((!file_exists(_PS_MANU_IMG_DIR_.'/'.(int) $manufacturer->id.'-medium_default.jpg')) ? $lang['iso_code'].'-default' : (int) $manufacturer->id).'-medium_default.jpg';
            $imageLink = (!in_array(rtrim(Context::getContext()->shop->virtual_uri, '/'), explode('/', $imageLink))) ? str_replace(
                [
                    'https',
                    Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri,
                ], [
                'http',
                Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri.Context::getContext()->shop->virtual_uri,
            ], $imageLink
            ) : $imageLink;

            $fileHeaders = (Configuration::get('SITEMAP_CHECK_IMAGE_FILE')) ? @get_headers($imageLink) : true;
            $manifacturerImage = [];
            if ($fileHeaders[0] != 'HTTP/1.1 404 Not Found' || $fileHeaders === true) {
                $manifacturerImage = [
                    'title_img' => htmlspecialchars(strip_tags($manufacturer->name)),
                    'caption'   => htmlspecialchars(strip_tags($manufacturer->short_description)),
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
                    'image'   => $manifacturerImage,
                ],
                $lang['iso_code'],
                $index,
                $i,
                $manufacturerId['id_manufacturer']
            )) {
                return false;
            }
        }

        return true;
    }

    protected function tableColumnExists($tableName, $column = null)
    {
        if (array_key_exists($tableName, $this->sql_checks)) {
            if (!empty($column) && array_key_exists($column, $this->sql_checks[$tableName])) {
                return $this->sql_checks[$tableName][$column];
            } else {
                return $this->sql_checks[$tableName];
            }
        }

        $table = Db::getInstance()->ExecuteS('SHOW TABLES LIKE \''.$tableName.'\'');
        if (empty($column)) {
            if (count($table) < 1) {
                return $this->sql_checks[$tableName] = false;
            } else {
                $this->sql_checks[$tableName] = true;
            }
        } else {
            $table = Db::getInstance()->ExecuteS('SELECT * FROM `'.$tableName.'` LIMIT 1');

            return $this->sql_checks[$tableName][$column] = array_key_exists($column, current($table));
        }

        return true;
    }

    /**
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param string $lang        language of link to add
     * @param int    $index       index of the current Google Sitemap file
     * @param int    $i           count of elements added to sitemap main array
     * @param int    $idSupplier  supplier object identifier
     *
     * @return bool
     */
    protected function _getSupplierLink(&$linkSitemap, $lang, &$index, &$i, $idSupplier = 0)
    {
        $link = new Link();
        if (method_exists('ShopUrl', 'resetMainDomainCache')) {
            ShopUrl::resetMainDomainCache();
        }
        $suppliersId = Db::getInstance()->ExecuteS(
            'SELECT s.`id_supplier` FROM `'._DB_PREFIX_.'supplier` s
			INNER JOIN `'._DB_PREFIX_.'supplier_lang` sl ON s.`id_supplier` = sl.`id_supplier` '.
            ($this->tableColumnExists(_DB_PREFIX_.'supplier_shop') ? 'INNER JOIN `'._DB_PREFIX_.'supplier_shop` ss ON s.`id_supplier` = ss.`id_supplier`' : '').'
			WHERE s.`active` = 1 AND s.`id_supplier` >= '.(int) $idSupplier.
            ($this->tableColumnExists(_DB_PREFIX_.'supplier_shop') ? ' AND ss.`id_shop` = '.(int) $this->context->shop->id : '').'
			AND sl.`id_lang` = '.(int) $lang['id_lang'].'
			ORDER BY s.`id_supplier` ASC'
        );
        foreach ($suppliersId as $supplierId) {
            $supplier = new Supplier((int) $supplierId['id_supplier'], $lang['id_lang']);
            $url = $link->getSupplierLink($supplier, $supplier->link_rewrite, $lang['id_lang']);

            $imageLink = 'http://'.Tools::getMediaServer(_THEME_SUP_DIR_)._THEME_SUP_DIR_.((!file_exists(_THEME_SUP_DIR_.'/'.(int) $supplier->id.'-medium_default.jpg')) ? $lang['iso_code'].'-default' : (int) $supplier->id).'-medium_default.jpg';
            $imageLink = (!in_array(rtrim(Context::getContext()->shop->virtual_uri, '/'), explode('/', $imageLink))) ? str_replace(
                [
                    'https',
                    Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri,
                ], [
                'http',
                Context::getContext()->shop->domain.Context::getContext()->shop->physical_uri.Context::getContext()->shop->virtual_uri,
            ], $imageLink
            ) : $imageLink;

            $fileHeaders = (Configuration::get('SITEMAP_CHECK_IMAGE_FILE')) ? @get_headers($imageLink) : true;
            $supplierImage = [];
            if ($fileHeaders[0] != 'HTTP/1.1 404 Not Found' || $fileHeaders === true) {
                $supplierImage = [
                    'title_img' => htmlspecialchars(strip_tags($supplier->name)),
                    'link'      => 'http'.(Configuration::get('PS_SSL_ENABLED') ? 's' : '').'://'.Tools::getMediaServer(_THEME_SUP_DIR_)._THEME_SUP_DIR_.((!file_exists(_THEME_SUP_DIR_.'/'.(int) $supplier->id.'-medium_default.jpg')) ? $lang['iso_code'].'-default' : (int) $supplier->id).'-medium_default.jpg',
                ];
            }
            if (!$this->_addLinkToSitemap(
                $linkSitemap, [
                'type'    => 'supplier',
                'page'    => 'supplier',
                'lastmod' => $supplier->date_upd,
                'link'    => $url,
                'image'   => $supplierImage,
            ], $lang['iso_code'], $index, $i, $supplierId['id_supplier']
            )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * return the link elements for the CMS object
     *
     * @param array  $linkSitemap contain all the links for the Google Sitemap file to be generated
     * @param string $lang        the language of link to add
     * @param int    $index       the index of the current Google Sitemap file
     * @param int    $i           the count of elements added to sitemap main array
     * @param int    $idCms       the CMS object identifier
     *
     * @return bool
     */
    protected function _getCmsLink(&$linkSitemap, $lang, &$index, &$i, $idCms = 0)
    {
        $link = new Link();
        if (method_exists('ShopUrl', 'resetMainDomainCache')) {
            ShopUrl::resetMainDomainCache();
        }
        $cmssId = Db::getInstance()->ExecuteS(
            'SELECT c.`id_cms` FROM `'._DB_PREFIX_.'cms` c INNER JOIN `'._DB_PREFIX_.'cms_lang` cl ON c.`id_cms` = cl.`id_cms` '.
            ($this->tableColumnExists(_DB_PREFIX_.'supplier_shop') ? 'INNER JOIN `'._DB_PREFIX_.'cms_shop` cs ON c.`id_cms` = cs.`id_cms` ' : '').
            'INNER JOIN `'._DB_PREFIX_.'cms_category` cc ON c.id_cms_category = cc.id_cms_category AND cc.active = 1
				WHERE c.`active` =1 AND c.`indexation` =1 AND c.`id_cms` >= '.(int) $idCms.
            ($this->tableColumnExists(_DB_PREFIX_.'supplier_shop') ? ' AND cs.id_shop = '.(int) $this->context->shop->id : '').
            ' AND cl.`id_lang` = '.(int) $lang['id_lang'].
            ' ORDER BY c.`id_cms` ASC'
        );

        if (is_array($cmssId)) {
            foreach ($cmssId as $cmsId) {
                $cms = new CMS((int) $cmsId['id_cms'], $lang['id_lang']);
                $cms->link_rewrite = urlencode((is_array($cms->link_rewrite) ? $cms->link_rewrite[(int) $lang['id_lang']] : $cms->link_rewrite));
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
                    $cmsId['id_cms']
                )) {
                    return false;
                }
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
     * @param string $lang        the language being processed
     * @param int    $index       the index of the current Google Sitemap file
     * @param int    $i           the count of elements added to sitemap main array
     * @param int    $numLink     restart at link number #$num_link
     *
     * @return boolean
     */
    protected function _getModuleLink(&$linkSitemap, $lang, &$index, &$i, $numLink = 0)
    {
        $modulesLinks = Hook::exec(self::HOOK_ADD_URLS, ['lang' => $lang], null, true);
        if (empty($modulesLinks) || !is_array($modulesLinks)) {
            return true;
        }
        $links = [];
        foreach ($modulesLinks as $moduleLinks) {
            $links = array_merge($links, $moduleLinks);
        }
        foreach ($moduleLinks as $n => $link) {
            if ($numLink > $n) {
                continue;
            }
            $link['type'] = 'module';
            if (!$this->_addLinkToSitemap($linkSitemap, $link, $lang['iso_code'], $index, $i, $n)) {
                return false;
            }
        }

        return true;
    }
}
