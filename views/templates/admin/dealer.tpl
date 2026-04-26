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
        <i class="icon-paste"></i>
        {l s='Dealer Copy-Paste Import' mod='mtbmodelimporter'}
    </div>
    <div class="panel-body">
        <p class="help-block">
            {l s='Paste the dealer product list text below. Click Analyze to preview the parsed data, then Save to store it.' mod='mtbmodelimporter'}
        </p>
        <form method="post" action="{$dealerUrl|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
            <div class="form-group">
                <label for="dealer_paste">{l s='Dealer Text' mod='mtbmodelimporter'}</label>
                <textarea
                    id="dealer_paste"
                    name="dealer_paste"
                    class="form-control"
                    rows="15"
                    placeholder="{l s='Paste dealer product list here...' mod='mtbmodelimporter'}"
                >{$pasteText|escape:'htmlall':'UTF-8'}</textarea>
            </div>
            <div class="btn-group">
                <button type="submit" name="submitAnalyze" value="1" class="btn btn-default">
                    <i class="icon-search"></i> {l s='Analyze' mod='mtbmodelimporter'}
                </button>
                {if $parsedResults|@count > 0}
                <button type="submit" name="submitSave" value="1" class="btn btn-primary">
                    <i class="icon-save"></i> {l s='Save' mod='mtbmodelimporter'} ({$parsedResults|@count})
                </button>
                {/if}
            </div>
        </form>
    </div>
</div>

{if $parsedResults|@count > 0}
<div class="panel">
    <div class="panel-heading">
        {l s='Parsed Products' mod='mtbmodelimporter'} ({$parsedResults|@count})
    </div>
    <div class="panel-body">
        <table class="table table-bordered table-striped table-condensed">
            <thead>
                <tr>
                    <th>{l s='Name' mod='mtbmodelimporter'}</th>
                    <th>{l s='Scale' mod='mtbmodelimporter'}</th>
                    <th>{l s='EAN' mod='mtbmodelimporter'}</th>
                    <th>{l s='Price' mod='mtbmodelimporter'}</th>
                    <th>{l s='Category' mod='mtbmodelimporter'}</th>
                    <th>{l s='Order Status' mod='mtbmodelimporter'}</th>
                    <th>{l s='Bearings' mod='mtbmodelimporter'}</th>
                    <th>{l s='DCC' mod='mtbmodelimporter'}</th>
                    <th>{l s='Note' mod='mtbmodelimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $parsedResults as $row}
                <tr>
                    <td>{$row.supplier_raw_name|escape:'htmlall':'UTF-8'}</td>
                    <td>{$row.scale|escape:'htmlall':'UTF-8'}</td>
                    <td>{$row.ean|default:''|escape:'htmlall':'UTF-8'}</td>
                    <td>{$row.price|default:''|escape:'htmlall':'UTF-8'}</td>
                    <td>{$row.category|escape:'htmlall':'UTF-8'}</td>
                    <td>
                        <span class="label label-{if $row.order_status eq 'available'}success{elseif $row.order_status eq 'suspended'}warning{else}danger{/if}">
                            {$row.order_status|escape:'htmlall':'UTF-8'}
                        </span>
                    </td>
                    <td>{if $row.has_bearings}<i class="icon-check text-success"></i>{else}&ndash;{/if}</td>
                    <td>{if $row.has_integrated_dcc}<i class="icon-check text-success"></i>{else}&ndash;{/if}</td>
                    <td>{$row.note|escape:'htmlall':'UTF-8'}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
{/if}
