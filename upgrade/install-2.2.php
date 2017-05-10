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

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_2($object, $install = false)
{
    if ($object->active || $install) {
        Configuration::updateValue('SITEMAP_PRIORITY_HOME', 1.0);
        Configuration::updateValue('SITEMAP_PRIORITY_PRODUCT', 0.9);
        Configuration::updateValue('SITEMAP_PRIORITY_CATEGORY', 0.8);
        Configuration::updateValue('SITEMAP_PRIORITY_MANUFACTURER', 0.7);
        Configuration::updateValue('SITEMAP_PRIORITY_SUPPLIER', 0.6);
        Configuration::updateValue('SITEMAP_PRIORITY_CMS', 0.5);
        Configuration::updateValue('SITEMAP_FREQUENCY', 'weekly');
        Configuration::updateValue('SITEMAP_LAST_EXPORT', false);

        return Db::getInstance()->Execute('DROP TABLE IF  EXISTS `'._DB_PREFIX_.'sitemap_sitemap`') && Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'sitemap_sitemap` (`link` varchar(255) DEFAULT NULL, `id_shop` int(11) DEFAULT 0) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');
    }
    $object->upgrade_detail['2.2'][] = 'GSitemap upgrade error !';

    return false;
}
