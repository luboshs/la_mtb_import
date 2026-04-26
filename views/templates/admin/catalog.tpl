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
        <i class="icon-globe"></i>
        {l s='Public Catalog Sync' mod='mtbmodelimporter'}
    </div>
    <div class="panel-body">
        <form method="post" action="{$syncUrl|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
            <div class="row">
                <div class="col-lg-3">
                    <div class="form-group">
                        <label>{l s='Scale' mod='mtbmodelimporter'}</label>
                        <select name="scale" class="form-control">
                            <option value="">{l s='All scales' mod='mtbmodelimporter'}</option>
                            {foreach $scales as $scale}
                            <option value="{$scale|escape:'htmlall':'UTF-8'}" {if $currentScale eq $scale}selected="selected"{/if}>
                                {$scale|escape:'htmlall':'UTF-8'}
                            </option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="form-group">
                        <label>{l s='Filter by status' mod='mtbmodelimporter'}</label>
                        <select name="filter_status" class="form-control">
                            <option value="">{l s='All statuses' mod='mtbmodelimporter'}</option>
                            <option value="new" {if $currentStatus eq 'new'}selected="selected"{/if}>{l s='New' mod='mtbmodelimporter'}</option>
                            <option value="changed" {if $currentStatus eq 'changed'}selected="selected"{/if}>{l s='Changed' mod='mtbmodelimporter'}</option>
                            <option value="ready" {if $currentStatus eq 'ready'}selected="selected"{/if}>{l s='Ready' mod='mtbmodelimporter'}</option>
                            <option value="imported" {if $currentStatus eq 'imported'}selected="selected"{/if}>{l s='Imported' mod='mtbmodelimporter'}</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" name="action" value="filter" class="btn btn-default">
                                <i class="icon-search"></i> {l s='Filter' mod='mtbmodelimporter'}
                            </button>
                            <button type="submit" name="sync" value="1" class="btn btn-primary">
                                <i class="icon-refresh"></i> {l s='Sync Now' mod='mtbmodelimporter'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

{if $products|@count > 0}
<div class="panel">
    <div class="panel-heading">
        {l s='Products' mod='mtbmodelimporter'} ({$products|@count})
    </div>
    <div class="panel-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>{l s='Scale' mod='mtbmodelimporter'}</th>
                    <th>{l s='Raw Name' mod='mtbmodelimporter'}</th>
                    <th>{l s='Generated Name' mod='mtbmodelimporter'}</th>
                    <th>{l s='Status' mod='mtbmodelimporter'}</th>
                    <th>{l s='Source URL' mod='mtbmodelimporter'}</th>
                    <th>{l s='Updated' mod='mtbmodelimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $products as $product}
                <tr>
                    <td>{$product.scale|escape:'htmlall':'UTF-8'}</td>
                    <td>{$product.supplier_raw_name|escape:'htmlall':'UTF-8'}</td>
                    <td>{$product.generated_name|escape:'htmlall':'UTF-8'}</td>
                    <td>
                        <span class="label label-{if $product.status eq 'new'}default{elseif $product.status eq 'changed'}warning{elseif $product.status eq 'ready'}info{else}success{/if}">
                            {$product.status|escape:'htmlall':'UTF-8'}
                        </span>
                    </td>
                    <td>
                        {if $product.source_url}
                        <a href="{$product.source_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer">
                            {l s='View' mod='mtbmodelimporter'}
                        </a>
                        {/if}
                    </td>
                    <td>{$product.updated_at|escape:'htmlall':'UTF-8'}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
{else}
<div class="panel">
    <div class="panel-body">
        <p class="text-muted">{l s='No products found. Run a sync to fetch the catalog.' mod='mtbmodelimporter'}</p>
    </div>
</div>
{/if}
