{if isset($sitemap_refresh_page)}
    <div class="panel">
        <div class="panel-heading"><i class="icon icon-sitemap"></i> {l s='Your sitemaps' mod='sitemap'}</div>
        <p>{$sitemap_number|intval} {l s='Sitemaps were already created.' mod='sitemap'}</p>
        <form action="{$sitemap_refresh_page|escape:'htmlall':'UTF-8'}" method="post" id="sitemap_generate_sitmap">
            <input type="submit" class="btn btn-primary" value="{l s='Continue' mod='sitemap'}" style="display: none;">
        </form>
    </div>
{else}
    {foreach $sitemaps as $sitemap}
        <div class="panel">
            <div class="panel-heading"><i class="icon icon-sitemap"></i> {l s='Sitemaps for %s' mod='sitemap' sprintf=[$sitemap.shopName]}</div>
            {l s='Please set up the following Sitemap URL in your Google Webmaster account:' mod='sitemap'} <a href="{$sitemap.indexUrl|escape:'htmlall':'UTF-8'}" target="_blank">{$sitemap.indexUrl|escape:'htmlall':'UTF-8'}</a>
            <br>
            {l s='This URL is the master Sitemaps file. It refers to the following sub-sitemap files:' mod='sitemap'}
            <div style="margin:10px 0;">
                {foreach $sitemap.links as $sitemapLink}
                    <a style="margin-right:10px;" class="label label-primary" target="_blank" href="{$sitemapLink|escape:'htmlall':'UTF-8'}">
                        {$sitemapLink|escape:'htmlall':'UTF-8'}
                    </a>
                {/foreach}
            </div>
            <p class="alert alert-success">
                {l s='Your last update was made on this date:' mod='sitemap'}</b>
                {if $sitemap.lastExport}
                    <b>{$sitemap.lastExport|escape:'htmlall':'UTF-8'}</b>
                {else}
                    <b>{l s='Never' mod='sitemap'}
                {/if}
            </p>
        </div>
    {/foreach}
    {if $sitemap_customer_limit.max_exec_time < 30 && $sitemap_customer_limit.max_exec_time > 0}
        <p class="alert alert-warning">
            {l s='For a better use of the module, please make sure that you have' mod='sitemap'} {l s='a minimum max_execution_time value of 30 seconds.' mod='sitemap'} {l s='You can edit these limits in your php.ini file. For more details, please contact your hosting provider.' mod='sitemap'}
        </p>
    {/if}
    <form class="defaultForm form-horizontal" action="{$sitemap_form|escape:'htmlall':'UTF-8'}" method="post">
        <div class="panel">
            <div class="panel-heading"><i class="icon icon-cogs"></i> {l s='Configure your Sitemap' mod='sitemap'}</div>
            <p>{l s='Several Sitemaps files will be generated depending on how your server is configured and on the number of activated products in your catalog.' mod='sitemap'}</p>
            <div class="form-group">
                <label class="control-label col-lg-3" for="sitemap_frequency">{l s='How often do you update your store?' mod='sitemap'}</label>
                <div class="col-lg-9">
                    <select class="fixed-width-xxl" name="sitemap_frequency">
                        <option{if $sitemap_frequency == 'always'} selected="selected"{/if} value='always'>{l s='always' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'hourly'} selected="selected"{/if} value='hourly'>{l s='hourly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'daily'} selected="selected"{/if} value='daily'>{l s='daily' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'weekly' || $sitemap_frequency == ''} selected="selected"{/if} value='weekly'>{l s='weekly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'monthly'} selected="selected"{/if} value='monthly'>{l s='monthly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'yearly'} selected="selected"{/if} value='yearly'>{l s='yearly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'never'} selected="selected"{/if} value='never'>{l s='never' mod='sitemap'}</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3" for="products_image_type">{l s='Select image type for product images' mod='sitemap'}</label>
                <div class="col-lg-9">
                    <select class="fixed-width-xxl" name="products_image_type">
                        {foreach $imageTypes['products'] as $type}
                            <option{if $selectedImageTypes['products'] == $type} selected="selected"{/if} value='{$type}'>{$type}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3" for="categories_image_type">{l s='Select image type for category images' mod='sitemap'}</label>
                <div class="col-lg-9">
                    <select class="fixed-width-xxl" name="categories_image_type">
                        {foreach $imageTypes['categories'] as $type}
                            <option{if $selectedImageTypes['categories'] == $type} selected="selected"{/if} value='{$type}'>{$type}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3" for="manufacturers_image_type">{l s='Select image type for manufacturer images' mod='sitemap'}</label>
                <div class="col-lg-9">
                    <select class="fixed-width-xxl" name="manufacturers_image_type">
                        {foreach $imageTypes['manufacturers'] as $type}
                            <option{if $selectedImageTypes['manufacturers'] == $type} selected="selected"{/if} value='{$type}'>{$type}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3" for="suppliers_image_type">{l s='Select image type for supplier images' mod='sitemap'}</label>
                <div class="col-lg-9">
                    <select class="fixed-width-xxl" name="suppliers_image_type">
                        {foreach $imageTypes['suppliers'] as $type}
                            <option{if $selectedImageTypes['suppliers'] == $type} selected="selected"{/if} value='{$type}'>{$type}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            <div>
                <p class="help-block">{l s='Indicate the pages that you do not want to include in your sitemaps file:' mod='sitemap'}</p>
                <label for="sitemap_check_all">
                    <input type="button" name="sitemap_check_all" value="{l s='Check all' mod='sitemap'}" class="check btn btn-sm btn-primary">
                </label>
            </div>
            <div class="row">
                {foreach from=$store_metas item=store_meta}
                    <div class="col-md-4">
                        <div class="checkbox">
                            <label style="font-size:14px;">
                                <input type="checkbox" class="sitemap_metas" name="sitemap_meta[]"{if in_array($store_meta.id_meta, $sitemap_disable_metas)} checked="checked"{/if} value="{$store_meta.id_meta|intval}"> <b>[{$store_meta.page|escape:'htmlall':'UTF-8'}]</b> {if $store_meta.title != ''}- {$store_meta.title|escape:'htmlall':'UTF-8'}{/if}
                            </label>
                        </div>
                    </div>
                {/foreach}
            </div>
            <div style="margin-top: 15px;">
                <div class="form-group">
                    <input type="submit" class="btn btn-primary" name="SubmitGsitemap" value="{l s='Generate Sitemap' mod='sitemap'}">
                    <span class="help-block">{l s='This can take several minutes' mod='sitemap'}</span>
                </div>
            </div>
        </div>
    </form>
    <div class="panel">
        <div class="panel-heading"><i class="icon icon-calendar"></i> {l s='Sitemap CRON' mod='sitemap'}</div>
        <div class="alert alert-info">
            {l s='Add the URL below to your CRON jobs:' mod='sitemap'}<br>
            {foreach $sitemaps as $sitemap}
                {l s='For shop' mod='sitemap'} <b>{$sitemap.shopName}</b>: <a href="{$sitemap.cronLink|escape:'htmlall':'UTF-8'}" target="_blank">{$sitemap.cronLink|escape:'htmlall':'UTF-8'}</a><br>
            {/foreach}
        </div>
    </div>
{/if}

<script>
    $(document).ready(function() {
        if ($('.sitemap_metas:checked').length == $('.sitemap_metas').length) {
            $('.check').val("{l s='Uncheck all' mod='sitemap'}");
        }
        $('.check').toggle(function() {
            $('.sitemap_metas').attr('checked', 'checked');
            $(this).val("{l s='Uncheck all' mod='sitemap'}");
        }, function() {
            $('.sitemap_metas').removeAttr('checked');
            $(this).val("{l s='Check all' mod='sitemap'}");
        });
    });
</script>
