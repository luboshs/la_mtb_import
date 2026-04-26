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
        <i class="icon-list-alt"></i>
        {l s='Import Log' mod='mtbmodelimporter'}
    </div>
    <div class="panel-body">
        <form method="get" action="{$logUrl|escape:'htmlall':'UTF-8'}" class="form-inline">
            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
            <div class="form-group">
                <label>{l s='Filter by level:' mod='mtbmodelimporter'}</label>
                <select name="filter_level" class="form-control input-sm">
                    <option value="">{l s='All levels' mod='mtbmodelimporter'}</option>
                    {foreach $allowedLevels as $lvl}
                    <option value="{$lvl|escape:'htmlall':'UTF-8'}" {if $filterLevel eq $lvl}selected="selected"{/if}>
                        {$lvl|escape:'htmlall':'UTF-8'}
                    </option>
                    {/foreach}
                </select>
            </div>
            <button type="submit" class="btn btn-default btn-sm">
                <i class="icon-search"></i> {l s='Filter' mod='mtbmodelimporter'}
            </button>
        </form>
        <form method="post" action="{$logUrl|escape:'htmlall':'UTF-8'}" style="display:inline; margin-left:10px;">
            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="clear_level" value="{$filterLevel|escape:'htmlall':'UTF-8'}">
            <button type="submit" name="submitClearLog" value="1" class="btn btn-danger btn-sm"
                onclick="return confirm('{l s='Are you sure you want to clear these log entries?' mod='mtbmodelimporter' js=true}');">
                <i class="icon-trash"></i>
                {if $filterLevel}{l s='Clear filtered entries' mod='mtbmodelimporter'}{else}{l s='Clear all entries' mod='mtbmodelimporter'}{/if}
            </button>
        </form>
    </div>
</div>

{if $logs|@count > 0}
<div class="panel">
    <div class="panel-body">
        <table class="table table-bordered table-condensed table-striped">
            <thead>
                <tr>
                    <th style="width:80px;">{l s='ID' mod='mtbmodelimporter'}</th>
                    <th style="width:80px;">{l s='Level' mod='mtbmodelimporter'}</th>
                    <th>{l s='Message' mod='mtbmodelimporter'}</th>
                    <th style="width:120px;">{l s='Context' mod='mtbmodelimporter'}</th>
                    <th style="width:150px;">{l s='Date' mod='mtbmodelimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $logs as $logEntry}
                <tr>
                    <td>{$logEntry.id|intval}</td>
                    <td>
                        <span class="label label-{if $logEntry.level eq 'error'}danger{elseif $logEntry.level eq 'warning'}warning{else}info{/if}">
                            {$logEntry.level|escape:'htmlall':'UTF-8'}
                        </span>
                    </td>
                    <td>{$logEntry.message|escape:'htmlall':'UTF-8'}</td>
                    <td>
                        {if $logEntry.context}
                        <small><code>{$logEntry.context|escape:'htmlall':'UTF-8'|truncate:80:'...'}</code></small>
                        {/if}
                    </td>
                    <td>{$logEntry.created_at|escape:'htmlall':'UTF-8'}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
{else}
<div class="panel">
    <div class="panel-body">
        <p class="text-muted">{l s='No log entries found.' mod='mtbmodelimporter'}</p>
    </div>
</div>
{/if}
