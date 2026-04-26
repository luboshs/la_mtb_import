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

<div class="panel">
    <div class="panel-heading">
        <i class="icon-dashboard"></i>
        {l s='MTB Model Importer – Dashboard' mod='mtbmodelimporter'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading text-center">
                        <strong>{l s='New' mod='mtbmodelimporter'}</strong>
                    </div>
                    <div class="panel-body text-center">
                        <h2>{$counts.new|intval}</h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="panel panel-warning">
                    <div class="panel-heading text-center">
                        <strong>{l s='Changed' mod='mtbmodelimporter'}</strong>
                    </div>
                    <div class="panel-body text-center">
                        <h2>{$counts.changed|intval}</h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading text-center">
                        <strong>{l s='Ready' mod='mtbmodelimporter'}</strong>
                    </div>
                    <div class="panel-body text-center">
                        <h2>{$counts.ready|intval}</h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="panel panel-success">
                    <div class="panel-heading text-center">
                        <strong>{l s='Imported' mod='mtbmodelimporter'}</strong>
                    </div>
                    <div class="panel-body text-center">
                        <h2>{$counts.imported|intval}</h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="row" style="margin-top:20px;">
            <div class="col-lg-12">
                <a href="{$catalogUrl|escape:'htmlall':'UTF-8'}" class="btn btn-default">
                    <i class="icon-globe"></i> {l s='Public Catalog' mod='mtbmodelimporter'}
                </a>
                <a href="{$dealerUrl|escape:'htmlall':'UTF-8'}" class="btn btn-default">
                    <i class="icon-paste"></i> {l s='Dealer Import' mod='mtbmodelimporter'}
                </a>
                <a href="{$productsUrl|escape:'htmlall':'UTF-8'}" class="btn btn-default">
                    <i class="icon-list"></i> {l s='Suggestions' mod='mtbmodelimporter'}
                </a>
                <a href="{$settingsUrl|escape:'htmlall':'UTF-8'}" class="btn btn-default">
                    <i class="icon-cogs"></i> {l s='Settings' mod='mtbmodelimporter'}
                </a>
            </div>
        </div>
    </div>
</div>

{if $recentLogs|@count > 0}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-list-alt"></i>
        {l s='Recent Log Entries' mod='mtbmodelimporter'}
    </div>
    <div class="panel-body">
        <table class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th>{l s='Level' mod='mtbmodelimporter'}</th>
                    <th>{l s='Message' mod='mtbmodelimporter'}</th>
                    <th>{l s='Date' mod='mtbmodelimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $recentLogs as $logEntry}
                <tr>
                    <td>
                        <span class="label label-{if $logEntry.level eq 'error'}danger{elseif $logEntry.level eq 'warning'}warning{else}info{/if}">
                            {$logEntry.level|escape:'htmlall':'UTF-8'}
                        </span>
                    </td>
                    <td>{$logEntry.message|escape:'htmlall':'UTF-8'}</td>
                    <td>{$logEntry.created_at|escape:'htmlall':'UTF-8'}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
        <a href="{$logUrl|escape:'htmlall':'UTF-8'}" class="btn btn-link">
            {l s='View all logs' mod='mtbmodelimporter'} &rarr;
        </a>
    </div>
</div>
{/if}
