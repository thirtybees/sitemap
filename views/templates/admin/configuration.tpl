<div style="width: 700px; margin: 0 auto;">
</div>
{if isset($smarty.get.validation)}
    <div class="conf confirm" style="width: 710px; margin: 0 auto;">
        {l s='Your Sitemaps were successfully created. Please do not forget to setup the URL' mod='sitemap'} <a href="{$sitemap_store_url|escape:'htmlall':'UTF-8'}{$shop->id|intval}_index_sitemap.xml" target="_blank"><span style="text-decoration: underline;">{$sitemap_store_url|escape:'htmlall':'UTF-8'}{$shop->id|intval}_index_sitemap.xml</a></span> {l s='in your Google Webmaster account.' mod='sitemap'}
    </div>
    <br/>
{/if}
{if isset($google_maps_error)}
    <div class="error" style="width: 710px; margin: 0 auto;">
        {$google_maps_error|escape:'htmlall':'UTF-8'}<br/>
    </div>
{/if}
{if isset($sitemap_refresh_page)}
    <fieldset style="width: 700px; margin: 0 auto; text-align: center;">
        <legend><img src="{$module_dir|escape:'htmlall':'UTF-8'}logo.gif" alt=""/>{l s='Your Sitemaps' mod='sitemap'}</legend>
        <p>{$sitemap_number|intval} {l s='Sitemaps were already created.' mod='sitemap'}<br/>
        </p>
        <br/>
        <form action="{$sitemap_refresh_page|escape:'htmlall':'UTF-8'}" method="post" id="sitemap_generate_sitmap">
            <img src="../img/loader.gif" alt=""/>
            <input type="submit" class="button" value="{l s='Continue' mod='sitemap'}" style="display: none;"/>
        </form>
    </fieldset>
{else}
    {if $sitemap_links}
        <fieldset style="width: 700px; margin: 0 auto;">
            <legend><img src="{$module_dir|escape:'htmlall':'UTF-8'}logo.gif" alt=""/>{l s='Your Sitemaps' mod='sitemap'}</legend>
            {l s='Please set up the following Sitemap URL in your Google Webmaster account:' mod='sitemap'}<br/>
            <a href="{$sitemap_store_url|escape:'htmlall':'UTF-8'}{$shop->id|intval}_index_sitemap.xml" target="_blank"><span style="color: blue;">{$sitemap_store_url|escape:'htmlall':'UTF-8'}{$shop->id|intval}_index_sitemap.xml</span></a><br/><br/>
            {l s='This URL is the master Sitemaps file. It refers to the following sub-sitemap files:' mod='sitemap'}
            <div style="max-height: 220px; overflow: auto;">
                <ul>
                    {foreach from=$sitemap_links item=sitemap_link}
                        <li><a target="_blank" style="color: blue;" href="{$sitemap_store_url|escape:'htmlall':'UTF-8'}{$sitemap_link.link|escape:'htmlall':'UTF-8'}">{$sitemap_link.link|escape:'htmlall':'UTF-8'}</a></li>
                    {/foreach}
                </ul>
            </div>
            <p>{l s='Your last update was made on this date:' mod='sitemap'} {$sitemap_last_export|escape:'htmlall':'UTF-8'}</p>
        </fieldset>
    {/if}
    <br/>
    {if ($sitemap_customer_limit.max_exec_time < 30 && $sitemap_customer_limit.max_exec_time > 0) || ($sitemap_customer_limit.memory_limit < 128 && $sitemap_customer_limit.memory_limit > 0)}
        <div class="warn" style="width: 700px; margin: 0 auto;">
            <p>{l s='For a better use of the module, please make sure that you have' mod='sitemap'}<br/>
            <ul>
                {if $sitemap_customer_limit.memory_limit < 128 && $sitemap_customer_limit.memory_limit > 0}
                    <li>{l s='a minimum memory_limit value of 128 MB.' mod='sitemap'}</li>
                {/if}
                {if $sitemap_customer_limit.max_exec_time < 30 && $sitemap_customer_limit.max_exec_time > 0}
                    <li>{l s='a minimum max_execution_time value of 30 seconds.' mod='sitemap'}</li>
                {/if}
            </ul>
            {l s='You can edit these limits in your php.ini file. For more details, please contact your hosting provider.' mod='sitemap'}</p>
        </div>
    {/if}
    <br/>
    <form action="{$sitemap_form|escape:'htmlall':'UTF-8'}" method="post">
        <fieldset style="width: 700px; margin: 0 auto;">
            <legend><img src="{$module_dir|escape:'htmlall':'UTF-8'}logo.gif" alt=""/>{l s='Configure your Sitemap' mod='sitemap'}</legend>
            <p>{l s='Several Sitemaps files will be generated depending on how your server is configured and on the number of activated products in your catalog.' mod='sitemap'}<br/></p>
            <div class="margin-form">
                <label for="sitemap_frequency" style="width: 235px;">{l s='How often do you update your store?' mod='sitemap'}
                    <select name="sitemap_frequency">
                        <option{if $sitemap_frequency == 'always'} selected="selected"{/if} value='always'>{l s='always' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'hourly'} selected="selected"{/if} value='hourly'>{l s='hourly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'daily'} selected="selected"{/if} value='daily'>{l s='daily' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'weekly' || $sitemap_frequency == ''} selected="selected"{/if} value='weekly'>{l s='weekly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'monthly'} selected="selected"{/if} value='monthly'>{l s='monthly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'yearly'} selected="selected"{/if} value='yearly'>{l s='yearly' mod='sitemap'}</option>
                        <option{if $sitemap_frequency == 'never'} selected="selected"{/if} value='never'>{l s='never' mod='sitemap'}</option>
                    </select></label>
            </div>
            <label for="gsitemap_check_image_file" style="width: 526px;">{l s='Check this box if you wish to check the presence of the image files on the server' mod='sitemap'}
                <input type="checkbox" name="sitemap_check_image_file" value="1" {if $sitemap_check_image_file}checked{/if}></label>
            <label for="gsitemap_check_all" style="width: 526px;"><span>{l s='check all' mod='sitemap'}</span>
                <input type="checkbox" name="sitemap_check_all" value="1" class="check"></label>
            <br class="clear"/>
            <p for="sitemap_meta">{l s='Indicate the pages that you do not want to include in your Sitemaps file:' mod='sitemap'}</p>
            <ul>
                {foreach from=$store_metas item=store_meta}
                    <li style="float: left; width: 200px; margin: 1px;">
                        <input type="checkbox" class="sitemap_metas" name="sitemap_meta[]"{if in_array($store_meta.id_meta, $sitemap_disable_metas)} checked="checked"{/if} value="{$store_meta.id_meta|intval}"/> {$store_meta.title|escape:'htmlall':'UTF-8'} [{$store_meta.page|escape:'htmlall':'UTF-8'}]
                    </li>
                {/foreach}
            </ul>
            <br/>
            <div class="margin-form" style="clear: both;">
                <input type="submit" style="margin: 20px;" class="button" name="SubmitGsitemap" onclick="$('#sitemap_loader').show();" value="{l s='Generate Sitemap' mod='sitemap'}"/>{l s='This can take several minutes' mod='sitemap'}
            </div>
            <p id="sitemap_loader" style="text-align: center; display: none;"><img src="../img/loader.gif" alt=""/></p>
        </fieldset>
    </form>
    <br/>
    <p class="info" style="width: 680px; margin: 10px auto;">
        <b style="display: block; margin-top: 5px; margin-left: 3px;">{l s='You have two ways to generate Sitemap:' mod='sitemap'}</b><br/><br/>
        1. <b>{l s='Manually:' mod='sitemap'}</b> {l s='using the form above (as often as needed)' mod='sitemap'}<br/>
        <br/><span style="font-style: italic;">{l s='-or-' mod='sitemap'}</span><br/><br/>
        2. <b>{l s='Automatically:' mod='sitemap'}</b> {l s='Ask your hosting provider to setup a "Cron task" to load the following URL at the time you would like:' mod='sitemap'}
        <a href="{$sitemap_cron|escape:'htmlall':'UTF-8'}" target="_blank">{$sitemap_cron|escape:'htmlall':'UTF-8'}</a><br/><br/>
        {l s='It will automatically generate your XML Sitemaps.' mod='sitemap'}<br/><br/>
    </p>
{/if}
<script type="text/javascript">
  $(document).ready(function () {

    if ($('.sitemap_metas:checked').length == $('.sitemap_metas').length)
      $('.check').parent('label').children('span').html("{l s='uncheck all' mod='sitemap'}");


    $('.check').toggle(function () {
      $('.sitemap_metas').attr('checked', 'checked');
      $(this).parent('label').children('span').html("{l s='uncheck all' mod='sitemap'}");
    }, function () {
      $('.sitemap_metas').removeAttr('checked');
      $(this).parent('label').children('span').html("{l s='check all' mod='sitemap'}");
    });
  });
</script>
