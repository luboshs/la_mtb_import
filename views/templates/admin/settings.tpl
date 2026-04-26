{**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    luboshs
 * @copyright since 2026 luboshs
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}

{$settingsForm}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-clock-o"></i>
        {l s='Cron Job URL' mod='mtbmodelimporter'}
    </div>
    <div class="panel-body">
        <p>{l s='Use the following URL to schedule automatic catalog synchronization:' mod='mtbmodelimporter'}</p>
        <div class="input-group">
            <input
                type="text"
                id="cron_url"
                class="form-control"
                value="{$cronUrl|escape:'htmlall':'UTF-8'}"
                readonly="readonly"
            >
            <span class="input-group-btn">
                <button class="btn btn-default" type="button" onclick="
                    var el = document.getElementById('cron_url');
                    el.select();
                    document.execCommand('copy');
                ">
                    <i class="icon-copy"></i> {l s='Copy' mod='mtbmodelimporter'}
                </button>
            </span>
        </div>
        <p class="help-block" style="margin-top:10px;">
            {l s='Schedule this URL in your server cron (e.g. every 6 hours):' mod='mtbmodelimporter'}
            <code>0 */6 * * * curl -s "{$cronUrl|escape:'htmlall':'UTF-8'}" &gt; /dev/null</code>
        </p>
        <p class="help-block">
            <strong>{l s='Cron Token:' mod='mtbmodelimporter'}</strong>
            <code>{$cronToken|escape:'htmlall':'UTF-8'}</code>
        </p>
    </div>
</div>
