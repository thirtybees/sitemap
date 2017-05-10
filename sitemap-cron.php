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

include __DIR__.'/../../config/config.inc.php';
include __DIR__.'/../../init.php';
/* Check to security tocken */
if (substr(Tools::encrypt('sitemap/cron'), 0, 10) != Tools::getValue('token') || !Module::isInstalled('sitemap')) {
    die('Bad token');
}

/** @var Sitemap $sitemap */
$sitemap = Module::getInstanceByName('sitemap');
/* Check if the module is enabled */
if ($sitemap->active) {
    /* Check if the requested shop exists */
    $shops = Db::getInstance()->executeS('SELECT id_shop FROM `'._DB_PREFIX_.'shop`');
    $listIdShop = [];
    foreach ($shops as $shop) {
        $listIdShop[] = (int) $shop['id_shop'];
    }

    $idShop = (isset($_GET['id_shop']) && in_array($_GET['id_shop'], $listIdShop)) ? (int) $_GET['id_shop'] : (int) Configuration::get('PS_SHOP_DEFAULT');
    $sitemap->cron = true;

    /* for the main run initiat the sitemap's files name stored in the database */
    if (!isset($_GET['continue'])) {
        $sitemap->emptySitemap((int) $idShop);
    }

    /* Create the Google Sitemap's files */
    p($sitemap->createSitemap((int) $idShop));
}
